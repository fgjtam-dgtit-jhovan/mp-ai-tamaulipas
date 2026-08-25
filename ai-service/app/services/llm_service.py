# app/services/llm_service.py
import asyncio
import json
import logging
import os
from typing import List, Optional

import httpx
from fastapi import HTTPException
from pydantic import BaseModel, Field

OLLAMA_URL = os.getenv("OLLAMA_URL") or os.getenv("OLLAMA_BASE_URL", "http://ollama:11434")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL") or "qwen2.5:3b-instruct"
OLLAMA_NUM_CTX = int(os.getenv("OLLAMA_NUM_CTX", "4096"))
OLLAMA_TIMEOUT = float(os.getenv("OLLAMA_TIMEOUT", "240"))
OLLAMA_NUM_PREDICT = int(os.getenv("OLLAMA_NUM_PREDICT", "2400"))
logger = logging.getLogger(__name__)


# ── Fase 1a: SOLO clasificación de hechos (Motor de Hechos) ────────
class FactItem(BaseModel):
    information_type: str = Field(
        description="MANIFESTACION, EVIDENCIA, TESTIMONIO, DATO_TECNICO, HIPOTESIS o CONCLUSION"
    )
    content: str = Field(max_length=300, description="Fragmento breve y fiel de la narrativa")
    source: str = Field(
        max_length=100,
        description=(
            "A QUIÉN o QUÉ pertenece este fragmento, en 2-5 palabras. Ejemplos válidos: "
            "'declaración del denunciante', 'testimonio de vecino', 'dictamen pericial en "
            "criminalística', 'reporte de C5', 'fotografía anexa'. Si la narrativa no atribuye "
            "el dato a nadie específico, usa 'narrativa_de_la_carpeta'. NUNCA copies el contenido "
            "del hecho aquí — este campo identifica el ORIGEN, no repite el dato."
        ),
    )
    procedural_relation: str = Field(description="cargo, descargo o neutral")


class FactsOnlySchema(BaseModel):
    facts: List[FactItem] = Field(max_length=8)


# ── Fase 1b: SOLO elementos del tipo penal ──────────────────────────
class ElementStatus(BaseModel):
    element_id: int
    status: str = Field(description="ACREDITADO, FALTANTE o CONTRADICTORIO")
    evidence_found: Optional[str] = Field(None, max_length=300, description="Cita literal muy breve si está ACREDITADO")
    missing_reason: Optional[str] = Field(None, max_length=300, description="Razón breve y específica si el status es FALTANTE")


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
    "nombre breve del origen",
]


def _es_texto_plantilla(texto: str | None) -> bool:
    if not texto:
        return False
    normalizado = texto.strip().lower()
    return any(frase in normalizado for frase in _PLANTILLA_SOSPECHOSA)


def _detectar_contenido_plantilla(analysis: dict) -> list[str]:
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
            "num_predict": OLLAMA_NUM_PREDICT,
        },
        "stream": False,
    }

    content = None
    done_reason = None

    for attempt in range(3):
        try:
            async with httpx.AsyncClient(timeout=OLLAMA_TIMEOUT) as client:
                response = await client.post(f"{OLLAMA_URL}/api/chat", json=payload)
                response.raise_for_status()
                result = response.json()
                content = result["message"]["content"]
                done_reason = result.get("done_reason")
                break
        except httpx.HTTPStatusError as exc:
            logger.error("Ollama respondió HTTP %s: %s", exc.response.status_code, exc.response.text[:2000])
            raise HTTPException(
                status_code=502,
                detail=f"Error en Ollama ({exc.response.status_code}): {exc.response.text}",
            )
        except httpx.RequestError as exc:
            if attempt == 2:
                raise HTTPException(
                    status_code=503,
                    detail=f"Error de conexión con Ollama en {OLLAMA_URL}: {str(exc)}",
                )
            logger.warning("Ollama no está disponible; reintentando (%s/3): %s", attempt + 1, exc)
            await asyncio.sleep(2 ** attempt)

    if done_reason == "length" and not _retry:
        return await query_llm(
            system_prompt,
            user_prompt + "\nIMPORTANTE: responde de forma mucho más compacta; no copies la "
            "narrativa en ningún campo y cierra siempre el JSON.",
            schema,
            _retry=True,
        )

    try:
        parsed = json.loads(content)
    except json.JSONDecodeError as exc:
        logger.error("Ollama devolvió JSON inválido: %s; contenido: %s", exc, (content or "")[:2000])
        if not _retry:
            return await query_llm(
                system_prompt,
                user_prompt + "\nIMPORTANTE: la respuesta anterior quedó incompleta. Usa "
                "fragmentos breves, no copies la narrativa y cierra correctamente el JSON.",
                schema,
                _retry=True,
            )
        raise HTTPException(status_code=502, detail="El modelo no regresó un JSON válido.") from exc

    try:
        parsed = schema.model_validate(parsed).model_dump()
    except Exception as exc:
        logger.error("Ollama devolvió una estructura inválida: %s; contenido: %s", exc, (content or "")[:2000])
        raise HTTPException(status_code=502, detail="El modelo devolvió una estructura JSON inválida.") from exc

    hallazgos = _detectar_contenido_plantilla(parsed)

    if hallazgos and not _retry:
        refuerzo = (
            "\n\nADVERTENCIA: tu respuesta anterior repitió texto genérico de ejemplo o de "
            f"instrucción en estos campos: {', '.join(hallazgos)}. Esto NO es válido. Cada campo "
            "debe contener contenido derivado ÚNICA Y EXCLUSIVAMENTE de la narrativa real de este "
            "caso. Si genuinamente no hay información suficiente, usa FALTANTE con una "
            "missing_reason específica del caso, o deja el arreglo vacío."
        )
        return await query_llm(system_prompt, user_prompt + refuerzo, schema, _retry=True)

    if hallazgos and _retry:
        raise HTTPException(
            status_code=502,
            detail=(
                "El modelo insistió en devolver contenido de plantilla en: "
                f"{', '.join(hallazgos)}. No se generó el análisis para evitar presentar texto "
                "genérico como si fuera análisis real del caso."
            ),
        )

    return parsed