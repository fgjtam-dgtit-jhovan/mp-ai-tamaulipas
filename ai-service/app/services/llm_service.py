# app/services/llm_service.py
import json
import os
from typing import List, Optional

import httpx
from fastapi import HTTPException
from pydantic import BaseModel, Field

OLLAMA_URL = os.getenv("OLLAMA_URL") or os.getenv("OLLAMA_BASE_URL", "http://ollama:11434")

# IMPORTANTE: "llama3.2" a secas suele resolver a la variante de 3B.
# Confirma que OLLAMA_MODEL en tu .env apunte a lo que realmente
# descargaste con `ollama pull` (ahora mismo: qwen2.5:3b-instruct).
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL") or "qwen2.5:3b-instruct"

# Con dos llamadas más chicas en vez de una grande, cada una necesita
# menos contexto — pero ajusta según lo que tu máquina aguante.
OLLAMA_NUM_CTX = int(os.getenv("OLLAMA_NUM_CTX", "4096"))


# ── Fase 1: solo elementos del tipo penal ───────────────────────────
class ElementStatus(BaseModel):
    element_id: int
    status: str = Field(description="ACREDITADO, FALTANTE o CONTRADICTORIO")
    evidence_found: Optional[str] = Field(None, description="Cita breve si está ACREDITADO")
    missing_reason: Optional[str] = Field(None, description="Solo llenar si el status es FALTANTE")


class ElementsAnalysisSchema(BaseModel):
    elements_analysis: List[ElementStatus]


# ── Fase 2: auditoría de objetividad + diligencias ──────────────────
class ObjectivityAudit(BaseModel):
    cargo_elements: List[str]
    descargo_elements: List[str]
    bias_warning: Optional[str] = None


class SuggestedDiligence(BaseModel):
    action: str
    legal_basis: str
    purpose: str


class AuditSchema(BaseModel):
    objectivity_audit: ObjectivityAudit
    suggested_diligences: List[SuggestedDiligence]


# Frases señuelo conocidas — si el modelo las regresa tal cual, copió
# la plantilla en vez de razonar sobre el caso real.
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
    "cita breve o fragmento literal",
]


def _es_texto_plantilla(texto: str | None) -> bool:
    if not texto:
        return False
    normalizado = texto.strip().lower()
    return any(frase in normalizado for frase in _PLANTILLA_SOSPECHOSA)


def _detectar_contenido_plantilla(analysis: dict) -> list[str]:
    """
    Revisa recursivamente cualquier valor string dentro del dict
    parseado y regresa las rutas donde encontró texto de plantilla.
    Funciona igual para el resultado de fase 1 (elements_analysis)
    que para el de fase 2 (objectivity_audit / suggested_diligences).
    """
    hallazgos = []

    def _revisar(valor, ruta):
        if isinstance(valor, str):
            if _es_texto_plantilla(valor):
                hallazgos.append(ruta)
        elif isinstance(valor, dict):
            for k, v in valor.items():
                _revisar(v, f"{ruta}.{k}")
        elif isinstance(valor, list):
            for i, v in enumerate(valor):
                _revisar(v, f"{ruta}[{i}]")

    for k, v in analysis.items():
        _revisar(v, k)

    return hallazgos


async def query_llm(system_prompt: str, user_prompt: str, schema: type[BaseModel], _retry: bool = False) -> dict:
    """
    Llama al LLM con un schema específico (ElementsAnalysisSchema o
    AuditSchema) y regresa el dict ya parseado y validado contra
    contenido de plantilla. Lanza HTTPException si el modelo insiste
    en copiar texto genérico tras un reintento.
    """
    payload = {
        "model": OLLAMA_MODEL,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt},
        ],
        "format": schema.model_json_schema(),
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
        return await query_llm(system_prompt, user_prompt + refuerzo, schema, _retry=True)

    if hallazgos and _retry:
        raise HTTPException(
            status_code=502,
            detail=(
                "El modelo insistió en devolver contenido de plantilla en: "
                f"{', '.join(hallazgos)}. No se generó el análisis para evitar "
                "presentar texto genérico como si fuera análisis real del caso."
            ),
        )

    return parsed