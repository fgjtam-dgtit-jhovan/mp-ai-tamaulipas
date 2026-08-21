# app/services/llm_service.py
import os
import json
import httpx
from pydantic import BaseModel, Field
from typing import List, Optional
from fastapi import HTTPException

OLLAMA_URL = os.getenv("OLLAMA_URL") or os.getenv("OLLAMA_BASE_URL", "http://ollama:11434")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "llama3.2")

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


async def query_llm(system_prompt: str, user_prompt: str) -> str:
    payload = {
        "model": OLLAMA_MODEL,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt}
        ],
        "format": AnalysisSchema.model_json_schema(),
        "stream": False
    }

    try:
        async with httpx.AsyncClient(timeout=180.0) as client:
            response = await client.post(f"{OLLAMA_URL}/api/chat", json=payload)
            response.raise_for_status()
            result = response.json()
            return result["message"]["content"]
    except httpx.HTTPStatusError as exc:
        raise HTTPException(
            status_code=502, 
            detail=f"Error en Ollama ({exc.response.status_code}): {exc.response.text}"
        )
    except httpx.RequestError as exc:
        raise HTTPException(
            status_code=503, 
            detail=f"Error de conexión con Ollama en {OLLAMA_URL}: {str(exc)}"
        )