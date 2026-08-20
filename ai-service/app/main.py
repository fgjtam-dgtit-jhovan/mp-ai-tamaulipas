import os

from fastapi import FastAPI, HTTPException, Depends
from pydantic import BaseModel
from typing import List, Optional
from app.services.analyzer_service import analyze_case_file


app = FastAPI(
    title="MP-IA Tamaulipas — AI Service",
    description="Servicio de RAG jurídico, RAG de hechos y orquestación del LLM. "
    "No expuesto directamente al navegador: solo Laravel lo consume.",
    version="0.1.0",
)


@app.get("/health")
def health():
    """Verifica que el servicio y sus dependencias estén disponibles."""
    return {
        "status": "ok",
        "qdrant_url": os.getenv("QDRANT_URL", "no configurado"),
        "ollama_url": os.getenv("OLLAMA_URL", "no configurado"),
    }


@app.get("/")
def root():
    return {"service": "mp-ia-ai-service", "docs": "/docs"}


class AnalysisRequest(BaseModel):
    external_case_id: str
    external_offense_id: int
    fact_narrative: str

@app.post("/api/v1/analyze-case")
async def analyze_case(payload: AnalysisRequest):
    try:
        result = await analyze_case_file(
            case_id=payload.external_case_id,
            offense_id=payload.external_offense_id,
            narrative=payload.fact_narrative
        )
        return {"status": "success", "data": result}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))