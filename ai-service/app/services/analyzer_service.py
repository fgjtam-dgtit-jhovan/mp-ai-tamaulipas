# app/services/analyzer_service.py
import json
import httpx
from app.services.qdrant_service import search_legal_articles
from app.services.llm_service import query_llm

async def analyze_case_file(case_id: str, offense_id: int, narrative: str):
    # 1. Obtener los elementos constitutivos desde Laravel (ejemplo vía HTTP interno o DB)
    # Por ahora simulamos la lectura de los elementos creados en tu Seeder (Robo Simple)
    elements_criteria = [
        {"id": 1, "name": "Apoderamiento", "criteria": "Acción de remoción o sustracción del bien"},
        {"id": 2, "name": "Cosa Mueble", "criteria": "Objeto material tangible trasladable"},
        {"id": 3, "name": "Ajenuidad", "criteria": "Acreditación de titularidad de un tercero"},
        {"id": 4, "name": "Falta de Consentimiento", "criteria": "Declaración o indicio de falta de autorización"}
    ]

    # 2. Consultar RAG Jurídico en Qdrant
    legal_context = await search_legal_articles(query=narrative, limit=3)

    # 3. Construir Prompt de Estricta Verificación
    system_prompt = """
    Eres un Asistente de Auditoría Jurídica para el Ministerio Público (MP-IA).
    Tu tarea es evaluar la narrativa de hechos únicamente contra los elementos constitutivos proporcionados.
    NO inventes hechos. Si falta información para acreditar un elemento, márcalo explícitamente como "FALTANTE".
    Responde ÚNICAMENTE en formato JSON válido.
    """

    user_prompt = f"""
    HECHOS DE LA CARPETA:
    "{narrative}"

    MARCO JURÍDICO RELEVANTE:
    {legal_context}

    ELEMENTOS A EVALUAR:
    {json.dumps(elements_criteria, ensure_ascii=False)}

    Genera un JSON con la siguiente estructura exacta:
    {{
      "elements_analysis": [
        {{
          "element_id": 1,
          "status": "ACREDITADO|FALTANTE|CONTRADICTORIO",
          "evidence_found": "Texto breve o cita de los hechos",
          "missing_reason": "Razón si está faltante"
        }}
      ],
      "objectivity_audit": {{
        "cargo_elements": ["Puntos que incriminan"],
        "descargo_elements": ["Puntos que benefician o eximen al imputado"],
        "bias_warning": "Alerta si la investigación ignora líneas de descargo"
      }},
      "suggested_diligences": [
        {{
          "action": "Diligencia a solicitar",
          "legal_basis": "Fundamento legal obtenido del RAG",
          "purpose": "Elemento que busca acreditar"
        }}
      ]
    }}
    """

    # 4. Invocación al LLM
    response_json = await query_llm(system_prompt, user_prompt)
    return json.loads(response_json)