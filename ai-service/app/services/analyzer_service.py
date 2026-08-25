import json

from app.services.llm_service import AuditSchema, InitialAnalysisSchema, query_llm
from app.services.qdrant_service import search_legal_articles


# ── FASE 1: clasificar hechos y evaluar elementos en una sola llamada ─
async def _analisis_inicial(narrative: str, offense_name: str | None, offense_id: int,
                            elements: list, legal_context: list, legal_articles: list) -> dict:
    system_prompt = '''
    Eres un asistente de análisis penal para el Ministerio Público. Realiza DOS tareas sobre la
    misma narrativa y devuelve ambos resultados en el JSON solicitado.

    TAREA A — CLASIFICAR INFORMACIÓN:
    Extrae únicamente fragmentos expresados en la narrativa y clasifícalos sin agregar datos.
    Clasificaciones permitidas:
    - MANIFESTACION: lo que una persona afirma o declara.
    - EVIDENCIA: objeto, documento, fotografía, video o registro mencionado.
    - TESTIMONIO: declaración atribuida a un tercero.
    - DATO_TECNICO: dictamen, medición, resultado o informe técnico.
    - HIPOTESIS: posibilidad o explicación no confirmada.
    - CONCLUSION: afirmación presentada como resultado o comprobación.

    TAREA B — EVALUAR ELEMENTOS JURÍDICOS:
    Compara la narrativa con cada elemento jurídico y decide si está ACREDITADO, FALTANTE o CONTRADICTORIO.

    REGLAS COMUNES:
    1. Conserva el sentido y usa citas breves de la narrativa.
    2. No conviertas una manifestación en un hecho probado.
    3. No conviertas definiciones legales en hechos.
    4. Si no hay fragmentos de una clasificación, no la inventes.
    5. procedural_relation debe ser cargo, descargo o neutral según el contenido explícito.
    6. evidence_found debe ser una cita literal breve de la narrativa.
    7. missing_reason debe ser específica de este caso.
    8. Cada fragmento debe ser breve; no incluyas explicaciones fuera de los campos solicitados.
    9. Responde únicamente con JSON válido.
    '''
    user_prompt = f'''
    DELITO: {offense_name or f"ID {offense_id}"}

    NARRATIVA DE LA CARPETA:
    "{narrative}"

    MARCO JURÍDICO RECUPERADO:
    {json.dumps(legal_context, ensure_ascii=False)}

    ELEMENTOS JURÍDICOS:
    {json.dumps(elements, ensure_ascii=False)}

    ARTÍCULOS ASOCIADOS:
    {json.dumps(legal_articles, ensure_ascii=False)}

    Devuelve un JSON con las claves "facts" y "elements_analysis". Incluye como máximo 8 hechos.
    '''
    return await query_llm(system_prompt, user_prompt, InitialAnalysisSchema)


# ── FASE 2: auditar el resultado de fase 1 + sugerir diligencias ───
async def _auditar_objetividad(narrative: str, offense_name: str | None, offense_id: int,
                                elements_analysis: list, legal_context: list) -> dict:
    system_prompt = '''
    Eres el Auditor de Objetividad del Ministerio Público. Tu ÚNICA tarea es revisar un análisis de
    elementos YA GENERADO (no lo vuelvas a generar) y: (1) separar qué hechos de la narrativa
    incriminan al imputado (cargo) y cuáles lo favorecen o atenúan (descargo), (2) alertar si el
    análisis ignoró líneas de descargo, y (3) sugerir diligencias específicas SOLO para los elementos
    marcados FALTANTE o CONTRADICTORIO.

    REGLAS ESTRICTAS:
    1. Basa cargo_elements y descargo_elements en hechos CONCRETOS de la narrativa, citados o
       parafraseados de forma específica — nunca una categoría abstracta.
    2. Si no hay ningún hecho de descargo en la narrativa, dilo explícitamente en bias_warning en vez
       de inventar uno.
    3. Cada suggested_diligence debe nombrar la diligencia EXACTA relevante a un elemento FALTANTE o
       CONTRADICTORIO específico de ESTE caso — nunca una diligencia genérica aplicable a cualquier caso.
    4. Si TODOS los elementos ya están ACREDITADOS, suggested_diligences puede quedar vacío — no
       inventes diligencias innecesarias.
    5. PROHIBIDO: nunca repitas ni parafrasees las frases del EJEMPLO de abajo.
    6. Responde ÚNICAMENTE con JSON válido, sin texto adicional.
    '''

    user_prompt = f'''
    EJEMPLO DE FORMATO (caso ficticio de referencia — NO copies estos valores):

    {{
      "objectivity_audit": {{
        "cargo_elements": ["El imputado tomó el objeto mientras la víctima dormía, sin solicitar autorización"],
        "descargo_elements": ["No se menciona en la narrativa si el imputado tenía relación de convivencia o acceso autorizado previo a la vivienda"],
        "bias_warning": null
      }},
      "suggested_diligences": [
        {{"action": "Solicitar dictamen de identificación y valuación del teléfono celular sustraído", "legal_basis": "Art. 402 — Código Penal para el Estado de Tamaulipas", "purpose": "Acreditar el valor de lo robado para determinar la fracción aplicable del Art. 402"}}
      ]
    }}

    ---

    CASO REAL A AUDITAR (usa ÚNICAMENTE esta información):

    HECHOS DE LA CARPETA:
    "{narrative}"

    DELITO:
    {offense_name or f"ID {offense_id}"}

    MARCO JURÍDICO RECUPERADO DE QDRANT:
    {json.dumps(legal_context, ensure_ascii=False)}

    ANÁLISIS DE ELEMENTOS YA GENERADO (audítalo, no lo repitas):
    {json.dumps(elements_analysis, ensure_ascii=False)}

    Responde ahora con el JSON de auditoría del CASO REAL, no del ejemplo.
    '''

    return await query_llm(system_prompt, user_prompt, AuditSchema)


# ── Orquestador: llama a las dos fases en secuencia ─────────────────
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

    initial_result = await _analisis_inicial(
        narrative, offense_name, offense_id, elements, legal_context, legal_articles
    )
    facts = initial_result["facts"]
    elements_analysis = initial_result["elements_analysis"]

    audit_result = await _auditar_objetividad(
        narrative, offense_name, offense_id, elements_analysis, legal_context
    )

    return {
      "facts": facts,
        "elements_analysis": elements_analysis,
        "objectivity_audit": audit_result["objectivity_audit"],
        "suggested_diligences": audit_result["suggested_diligences"],
    }