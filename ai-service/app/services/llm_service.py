# app/services/llm_service.py
import asyncio
import json
import logging
import os
import time
from typing import List, Literal, Optional

import httpx
from fastapi import HTTPException
from pydantic import BaseModel, Field, model_validator

OLLAMA_URL = os.getenv("OLLAMA_URL") or os.getenv("OLLAMA_BASE_URL", "http://ollama:11434")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL") or "qwen2.5:3b-instruct"
OLLAMA_NUM_CTX = int(os.getenv("OLLAMA_NUM_CTX", "4096"))
_OLLAMA_TIMEOUT = float(os.getenv("OLLAMA_TIMEOUT", "150"))
OLLAMA_TIMEOUT: Optional[float] = None if _OLLAMA_TIMEOUT <= 0 else _OLLAMA_TIMEOUT

# 2400 era mucho más de lo que necesitan estos JSONs (cada campo de
# texto tiene max_length=300 caracteres). Un techo más bajo reduce el
# tiempo máximo posible por llamada en CPU sin arriesgar truncar
# contenido real — súbelo solo si empiezas a ver done_reason=="length"
# en los logs con contenido legítimamente cortado.
OLLAMA_NUM_PREDICT = int(os.getenv("OLLAMA_NUM_PREDICT", "700"))

# CRÍTICO para CPU: sin esto, Ollama decide solo cuántos hilos usar
# dentro del contenedor y no siempre acierta con el total real
# disponible. Ajusta el default a los núcleos de tu servidor.
OLLAMA_NUM_THREAD = int(os.getenv("OLLAMA_NUM_THREAD", str(os.cpu_count() or 8)))

logger = logging.getLogger(__name__)
logger.setLevel(logging.INFO)

if not logger.handlers:
    handler = logging.StreamHandler()
    handler.setLevel(logging.INFO)
    formatter = logging.Formatter(
        "%(asctime)s | %(levelname)s | %(name)s | %(message)s"
    )
    handler.setFormatter(formatter)
    logger.addHandler(handler)

logger.propagate = False


# ── Fase 1a: SOLO clasificación de hechos (Motor de Hechos) ────────
class FactItem(BaseModel):
    information_type: Literal[
        "MANIFESTACION", "EVIDENCIA", "TESTIMONIO", "DATO_TECNICO", "HIPOTESIS", "CONCLUSION"
    ] = Field(description="Clasificación del fragmento según su naturaleza.")
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
    procedural_relation: Literal["cargo", "descargo", "neutral"] = Field(
        description="Relación procesal del fragmento respecto al imputado."
    )
    is_confirmed: bool = Field(
        description=(
            "false si el propio texto señala incertidumbre (ej. 'por confirmar', 'presuntamente', "
            "'se reporta', 'sin confirmar', 'aparentemente'). true si se presenta como dato firme."
        )
    )


class FactsOnlySchema(BaseModel):
    facts: List[FactItem] = Field(max_length=8)


# ── Fase 1b: SOLO elementos del tipo penal ──────────────────────────
class ElementStatus(BaseModel):
    element_id: int
    status: Literal["ACREDITADO", "FALTANTE", "CONTRADICTORIO"] = Field(
        description="Estado del elemento jurídico frente a los hechos disponibles."
    )
    evidence_found: Optional[str] = Field(None, max_length=300, description="Cita literal muy breve si está ACREDITADO")
    missing_reason: Optional[str] = Field(None, max_length=300, description="Razón breve y específica si el status es FALTANTE")
    supporting_fact_id: Optional[str] = Field(
        None,
        description=(
            "Si status es ACREDITADO o CONTRADICTORIO, el valor EXACTO del campo 'id' "
            "(ej. 'f0', 'f1') del hecho de la lista que sustenta esta decisión. "
            "null si status es FALTANTE."
        ),
    )

    @model_validator(mode="after")
    def _consistencia_status(self) -> "ElementStatus":
        # Un modelo pequeño puede omitir campos relacionados. Ante eso,
        # degradamos de forma conservadora a FALTANTE en vez de fabricar
        # evidencia o abortar todo el análisis por un registro incompleto.
        if self.status in ("ACREDITADO", "CONTRADICTORIO"):
            if not self.evidence_found or not self.supporting_fact_id:
                self.status = "FALTANTE"
                self.evidence_found = None
                self.supporting_fact_id = None
                self.missing_reason = (
                    "La respuesta del modelo no aportó evidencia verificable para este elemento."
                )
        if self.status == "FALTANTE":
            if not self.missing_reason:
                self.missing_reason = "No se identificó información suficiente en los hechos disponibles."
        return self


class ElementsAnalysisSchema(BaseModel):
    elements_analysis: List[ElementStatus]

    @model_validator(mode="after")
    def _sin_element_ids_duplicados(self) -> "ElementsAnalysisSchema":
        # Conserva la primera aparición. El orquestador completa los elementos
        # omitidos y vuelve a validar evidencia contra los hechos reales.
        vistos = set()
        unicos = []
        for element in self.elements_analysis:
            if element.element_id not in vistos:
                vistos.add(element.element_id)
                unicos.append(element)
        self.elements_analysis = unicos
        return self


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
    "ningún hecho de facts",
    "ningún hecho en facts",
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


def _parsear_json_respuesta(content: str) -> dict:
    texto = content.strip()
    try:
        return json.loads(texto)
    except json.JSONDecodeError:
        inicio = texto.find("{")
        fin = texto.rfind("}")
        if inicio < 0 or fin <= inicio:
            raise
        return json.loads(texto[inicio:fin + 1])


async def query_llm(system_prompt: str, user_prompt: str, schema: type[BaseModel], _retry: bool = False,
                     _call_label: str = "") -> dict:
    payload = {
        "model": OLLAMA_MODEL,
        "system": system_prompt,
        "prompt": user_prompt,
        "format": schema.model_json_schema(),
        "options": {
            "temperature": 0.1,
            "top_p": 0.2,
            "seed": 42,
            "num_ctx": OLLAMA_NUM_CTX,
            "num_predict": OLLAMA_NUM_PREDICT,
            "num_thread": OLLAMA_NUM_THREAD,
        },
        "stream": False,
    }

    content = None
    done_reason = None
    eval_count = None
    eval_duration_s = 0
    inicio = time.perf_counter()

    for attempt in range(3):
        try:
            async with httpx.AsyncClient(timeout=OLLAMA_TIMEOUT) as client:
                response = await client.post(f"{OLLAMA_URL}/api/generate", json=payload)
                response.raise_for_status()
                result = response.json()
                content = result["response"]
                done_reason = result.get("done_reason")
                eval_count = result.get("eval_count")
                eval_duration_s = (result.get("eval_duration") or 0) / 1e9
                break
        except httpx.HTTPStatusError as exc:
            logger.error("Ollama respondió HTTP %s: %s", exc.response.status_code, exc.response.text[:2000])
            raise HTTPException(
                status_code=502,
                detail=f"Error en Ollama ({exc.response.status_code}): {exc.response.text}",
            )
        except httpx.TimeoutException as exc:
            raise HTTPException(
                status_code=504,
                detail=f"Ollama agotó el tiempo de respuesta en {OLLAMA_TIMEOUT}s: {str(exc)}",
            ) from exc
        except httpx.RequestError as exc:
            if attempt == 2:
                raise HTTPException(
                    status_code=503,
                    detail=f"Error de conexión con Ollama en {OLLAMA_URL}: {str(exc)}",
                )
            logger.warning("Ollama no está disponible; reintentando (%s/3): %s", attempt + 1, exc)
            await asyncio.sleep(2 ** attempt)

    elapsed = time.perf_counter() - inicio
    logger.info(
        "[%s] Ollama respondió en %.1fs (tokens generados: %s, tokens/s: %.1f, done_reason: %s, hilos: %s)",
        _call_label or "sin-etiqueta",
        elapsed,
        eval_count,
        (eval_count / eval_duration_s) if eval_count and eval_duration_s else 0.0,
        done_reason,
        OLLAMA_NUM_THREAD,
    )

    if done_reason == "length" and not _retry:
        logger.warning("[%s] Se agotó num_predict (%s) — reintentando con instrucción de compactar.",
                        _call_label, OLLAMA_NUM_PREDICT)
        return await query_llm(
            system_prompt,
            user_prompt + "\nIMPORTANTE: responde de forma mucho más compacta; no copies la "
            "narrativa en ningún campo y cierra siempre el JSON.",
            schema,
            _retry=True,
            _call_label=_call_label,
        )

    try:
        parsed = _parsear_json_respuesta(content or "")
    except json.JSONDecodeError as exc:
        logger.error("[%s] Ollama devolvió JSON inválido: %s; contenido: %s", _call_label, exc, (content or "")[:2000])
        if not _retry:
            return await query_llm(
                system_prompt,
                user_prompt + "\nIMPORTANTE: la respuesta anterior quedó incompleta. Usa "
                "fragmentos breves, no copies la narrativa y cierra correctamente el JSON.",
                schema,
                _retry=True,
                _call_label=_call_label,
            )
        raise HTTPException(status_code=502, detail="El modelo no regresó un JSON válido.") from exc

    try:
        parsed = schema.model_validate(parsed).model_dump()
    except Exception as exc:
        logger.error("[%s] Ollama devolvió una estructura inválida: %s; contenido: %s",
                      _call_label, exc, (content or "")[:2000])

        if not _retry:
            # A diferencia de antes, un fallo de validación (enum inválido,
            # campos requeridos según el status faltantes, element_id
            # duplicado, etc.) ahora también da pie a un reintento con el
            # error explícito — igual de barato que el retry por contenido
            # de plantilla, y evita tronar el análisis completo por un solo
            # campo mal llenado.
            return await query_llm(
                system_prompt,
                user_prompt + "\nIMPORTANTE: tu respuesta anterior no cumplió el formato "
                f"requerido ({str(exc)[:300]}). Revisa que 'status' sea EXACTAMENTE uno de "
                "ACREDITADO, FALTANTE o CONTRADICTORIO (nunca otro valor), que "
                "evidence_found/missing_reason/supporting_fact_id estén llenos según "
                    "corresponda a cada status. Usa CONTRADICTORIO solo si un hecho expresa "
                    "explícitamente lo contrario del elemento; si falta información, usa FALTANTE. "
                    "Cada element_id debe aparecer UNA SOLA VEZ "
                "en toda la lista.",
                schema,
                _retry=True,
                _call_label=_call_label,
            )

        raise HTTPException(status_code=502, detail="El modelo devolvió una estructura JSON inválida.") from exc

    hallazgos = _detectar_contenido_plantilla(parsed)

    if hallazgos and not _retry:
        logger.warning("[%s] Contenido de plantilla detectado en: %s — reintentando.", _call_label, hallazgos)
        refuerzo = (
            "\n\nADVERTENCIA: tu respuesta anterior repitió texto genérico de ejemplo o de "
            f"instrucción en estos campos: {', '.join(hallazgos)}. Esto NO es válido. Cada campo "
            "debe contener contenido derivado ÚNICA Y EXCLUSIVAMENTE de la narrativa real de este "
            "caso. Si genuinamente no hay información suficiente, usa FALTANTE con una "
            "missing_reason específica del caso, o deja el arreglo vacío."
        )
        return await query_llm(system_prompt, user_prompt + refuerzo, schema, _retry=True, _call_label=_call_label)

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