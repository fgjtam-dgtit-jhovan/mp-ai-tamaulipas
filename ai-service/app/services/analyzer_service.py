import json

from app.services.llm_service import query_llm
from app.services.qdrant_service import search_legal_articles


async def analyze_case_file(
    case_id: str,
    offense_id: int,
    narrative: str,
    offense_name: str | None,
    elements: list,
    legal_articles: list,
):
    if not elements:
        raise ValueError(f'El delito {offense_id} no tiene elementos jurídicos configurados.')

    legal_context = await search_legal_articles(query=narrative, offense_id=offense_id, limit=5)

    system_prompt = '''
    Eres un Asistente de Auditoría Jurídica para el Ministerio Público (MP-IA).
    Evalúa la narrativa únicamente contra los elementos y artículos proporcionados.
    No inventes hechos, normas, artículos ni evidencias. Si falta información, marca el elemento como FALTANTE.
    Responde ÚNICAMENTE en formato JSON válido.
    '''

    user_prompt = f'''
    HECHOS DE LA CARPETA:
    "{narrative}"

    DELITO:
    {offense_name or f"ID {offense_id}"}

    MARCO JURÍDICO RECUPERADO DE QDRANT:
    {json.dumps(legal_context, ensure_ascii=False)}

    ELEMENTOS A EVALUAR (fuente jurídica de PostgreSQL):
    {json.dumps(elements, ensure_ascii=False)}

    ARTÍCULOS ASOCIADOS:
    {json.dumps(legal_articles, ensure_ascii=False)}

    Genera exactamente esta estructura:
    {{
      "elements_analysis": [
        {{
          "element_id": "ID del elemento recibido",
          "status": "ACREDITADO|FALTANTE|CONTRADICTORIO",
          "evidence_found": "Texto breve o cita literal de los hechos",
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
          "legal_basis": "Fundamento legal recuperado",
          "purpose": "Elemento que busca acreditar"
        }}
      ]
    }}
    '''

    response_json = await query_llm(system_prompt, user_prompt)
    return json.loads(response_json)