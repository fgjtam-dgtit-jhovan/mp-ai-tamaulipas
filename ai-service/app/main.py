import os
import logging

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from typing import List, Optional
from app.services.analyzer_service import analyze_case_file


app = FastAPI(
    title="MP-IA Tamaulipas — AI Service",
    description="Servicio de RAG jurídico, RAG de hechos y orquestación del LLM. "
    "No expuesto directamente al navegador: solo Laravel lo consume.",
    version="0.1.0",
)
logger = logging.getLogger(__name__)


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
    offense_name: Optional[str] = None
    fact_narrative: str
    fact_date: Optional[str] = None
    elements: List[dict]
    legal_articles: List[dict] = Field(default_factory=list)

@app.post("/api/v1/analyze-case")
async def analyze_case(payload: AnalysisRequest):
    try:
        result = await analyze_case_file(
            case_id=payload.external_case_id,
            offense_id=payload.external_offense_id,
            narrative=payload.fact_narrative,
            offense_name=payload.offense_name,
            elements=payload.elements,
            legal_articles=payload.legal_articles,
            fact_date=payload.fact_date,
        )
        return {"status": "success", "data": result}
    except HTTPException:
        raise
    except Exception as exception:
        logger.exception('Error analizando la carpeta %s', payload.external_case_id)
        raise HTTPException(
            status_code=500,
            detail={
                'message': 'No fue posible completar el análisis de la carpeta.',
                'error': str(exception),
            },
        ) from exception