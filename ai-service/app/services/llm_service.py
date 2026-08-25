# app/services/llm_service.py
import json
import os
from typing import List, Optional

import httpx
from fastapi import HTTPException
from pydantic import BaseModel, Field

OLLAMA_URL = os.getenv("OLLAMA_URL") or os.getenv("OLLAMA_BASE_URL", "http://ollama:11434")

# IMPORTANTE: "llama3.2" a secas suele resolver a la variante de 3B —
# insuficiente para razonamiento jurídico multi-restricción. Usa un
# modelo más capaz por default y confirma que OLLAMA_MODEL en tu .env
# apunte a lo que realmente descargaste con `ollama pull`.
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL") or "qwen2.5:14b"

# Contexto más amplio: narrativa + legal_context (RAG) + elements +
# legal_articles + prompts fácilmente pasan de 4096 tokens. Si tu
# hardware no soporta 8192 con este modelo, baja a un modelo más chico
# ANTES de bajar num_ctx — la verdad, un contexto truncado es peor que
# un modelo algo más lento.
OLLAMA_NUM_CTX = int(os.getenv("OLLAMA_NUM_CTX", "8192"))


class ElementStatus(BaseModel):
    element_id: int
    status: str = Field(description="ACREDITADO, FALTANTE o CONTRADICTORIO")
    evidence_found: Optional[str] = Field(None, description="Cita breve si está ACREDITADO")
    missing_reason: Optional[str] = Field(None, description="Solo llenar si el status es FALTANTE")


class ObjectivityAudit(BaseModel):
    cargo_elements: List[str]
    descargo_elements: List[str]
    bias_warning: Optional[str] = None


class SuggestedDiligence(BaseModel):
    action: str
    legal_basis: str
    purpose: str


class AnalysisSchema(BaseModel):
    elements_analysis: List[ElementStatus]
    objectivity_audit: ObjectivityAudit
    suggested_diligences: List[SuggestedDiligence]


# Frases "señuelo" que sabemos que aparecían en versiones previas del
# prompt como placeholders — si el modelo las regresa tal cual, es
# indicio casi seguro de que copió la plantilla en vez de razonar
# sobre el caso real. Amplía esta lista si detectas nuevos patrones.
_PLANTILLA_SOSPECHOSA = [
    "diligencia a solicitar",
    "fundamento legal recuperado",
    "elemento que busca acreditar",
    "puntos que incriminan",
    "puntos que benefician o eximen al imputado",
    "hechos concretos de la narrativa que incriminan al imputado",
    "hechos concretos de la narrativa que favorecen, eximen o atenúan la responsabilidad",
    "diligencia específica",
    "acción a realizar",
]


def _es_texto_plantilla(texto: str | None) -> bool:
    if not texto:
        return False
    normalizado = texto.strip().lower()
    return any(frase in normalizado for frase in _PLANTILLA_SOSPECHOSA)


def _detectar_contenido_plantilla(analysis: dict) -> list[str]:
    """
    Revisa el resultado ya parseado y regresa una lista de rutas de
    campos que parecen contener texto copiado de la plantilla del
    prompt en vez de análisis real del caso. Lista vacía = todo bien.
    """
    hallazgos = []

    for i, el in enumerate(analysis.get("elements_analysis", [])):
        if _es_texto_plantilla(el.get("evidence_found")):
            hallazgos.append(f"elements_analysis[{i}].evidence_found")
        if _es_texto_plantilla(el.get("missing_reason")):
            hallazgos.append(f"elements_analysis[{i}].missing_reason")

    audit = analysis.get("objectivity_audit", {})
    for i, item in enumerate(audit.get("cargo_elements", [])):
        if _es_texto_plantilla(item):
            hallazgos.append(f"objectivity_audit.cargo_elements[{i}]")
    for i, item in enumerate(audit.get("descargo_elements", [])):
        if _es_texto_plantilla(item):
            hallazgos.append(f"objectivity_audit.descargo_elements[{i}]")

    for i, dil in enumerate(analysis.get("suggested_diligences", [])):
        if _es_texto_plantilla(dil.get("action")):
            hallazgos.append(f"suggested_diligences[{i}].action")
        if _es_texto_plantilla(dil.get("legal_basis")):
            hallazgos.append(f"suggested_diligences[{i}].legal_basis")
        if _es_texto_plantilla(dil.get("purpose")):
            hallazgos.append(f"suggested_diligences[{i}].purpose")

    return hallazgos


async def query_llm(system_prompt: str, user_prompt: str, _retry: bool = False) -> str:
    payload = {
        "model": OLLAMA_MODEL,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt},
        ],
        "format": AnalysisSchema.model_json_schema(),
        "options": {
            "temperature": 0.1,
            "top_p": 0.2,
            "seed": 42,
            "num_ctx": OLLAMA_NUM_CTX,
        },
        "stream": False,
    }

    try:
        async with httpx.AsyncClient(timeout=180.0) as client:
            response = await client.post(f"{OLLAMA_URL}/api/chat", json=payload)
            response.raise_for_status()
            result = response.json()
            content = result["message"]["content"]
    except httpx.HTTPStatusError as exc:
        raise HTTPException(
            status_code=502,
            detail=f"Error en Ollama ({exc.response.status_code}): {exc.response.text}",
        )
    except httpx.RequestError as exc:
        raise HTTPException(
            status_code=503,
            detail=f"Error de conexión con Ollama en {OLLAMA_URL}: {str(exc)}",
        )

    # Validación defensiva: si el resultado parece copiar la plantilla,
    # no lo aceptamos en silencio. Reintentamos UNA vez con una
    # instrucción explícita de no repetir texto genérico; si vuelve a
    # fallar, preferimos un error claro a un análisis falso — el mismo
    # principio de "no puedo concluir" que exige el anteproyecto.
    try:
        parsed = json.loads(content)
    except json.JSONDecodeError:
        raise HTTPException(status_code=502, detail="El modelo no regresó un JSON válido.")

    hallazgos = _detectar_contenido_plantilla(parsed)

    if hallazgos and not _retry:
        refuerzo = (
            "\n\nADVERTENCIA: tu respuesta anterior repitió texto genérico de ejemplo "
            f"en estos campos: {', '.join(hallazgos)}. Esto NO es válido. Cada campo debe "
            "contener contenido derivado ÚNICA Y EXCLUSIVAMENTE de la narrativa real de "
            "este caso. Si genuinamente no hay información suficiente, usa FALTANTE con "
            "una missing_reason específica del caso, o deja el arreglo vacío — nunca "
            "repitas una frase de ejemplo."
        )
        return await query_llm(system_prompt, user_prompt + refuerzo, _retry=True)

    if hallazgos and _retry:
        raise HTTPException(
            status_code=502,
            detail=(
                "El modelo insistió en devolver contenido de plantilla en: "
                f"{', '.join(hallazgos)}. No se generó el análisis para evitar "
                "presentar texto genérico como si fuera análisis real del caso."
            ),
        )

    return content