import json

from app.services.llm_service import AuditSchema, ElementsAnalysisSchema, FactsOnlySchema, query_llm
from app.services.qdrant_service import search_legal_articles


# ── FASE 1a: SOLO clasificar hechos (Motor de Hechos) ───────────────
async def _clasificar_hechos(narrative: str) -> list:
    system_prompt = '''
    Eres un asistente de análisis penal para el Ministerio Público. Tu ÚNICA tarea es clasificar
    fragmentos de una narrativa según su naturaleza. NO evalúes elementos jurídicos, NO hagas nada
    más que esto.

    CLASIFICACIONES PERMITIDAS:
    - MANIFESTACION: lo que una persona afirma o declara (ej. "yo iba caminando cuando...").
    - EVIDENCIA: objeto, documento, fotografía, video o registro físico mencionado.
    - TESTIMONIO: declaración atribuida a un tercero distinto del declarante principal.
    - DATO_TECNICO: dictamen, medición, resultado o informe técnico/pericial.
    - HIPOTESIS: posibilidad o explicación no confirmada ("podría tratarse de...").
    - CONCLUSION: afirmación presentada como resultado o comprobación ("se comprobó que...").

    REGLAS ESTRICTAS:
    1. Extrae SOLO fragmentos que aparecen literalmente en la narrativa. No agregues información.
    2. No conviertas una manifestación en un hecho probado ni en conclusión.
    3. Si la narrativa no tiene fragmentos de una clasificación, simplemente no la incluyas.
    4. Máximo 8 hechos — prioriza los más relevantes para el caso.
    5. PROHIBIDO: nunca repitas ni parafrasees las frases del EJEMPLO de abajo.
    6. Responde ÚNICAMENTE con JSON válido.
    '''

    user_prompt = f'''
    EJEMPLO DE FORMATO (caso ficticio de referencia — NO copies estos valores):

    Narrativa de ejemplo: "...el denunciante manifestó que vio al imputado tomar el teléfono de
    la mesa. Un vecino declaró haber escuchado gritos. Se anexa fotografía del lugar..."

    {{
      "facts": [
        {{"information_type": "MANIFESTACION", "content": "vio al imputado tomar el teléfono de la mesa", "source": "declaración del denunciante", "procedural_relation": "cargo"}},
        {{"information_type": "TESTIMONIO", "content": "escuchó gritos", "source": "testimonio de vecino", "procedural_relation": "cargo"}},
        {{"information_type": "EVIDENCIA", "content": "fotografía del lugar de los hechos", "source": "anexo fotográfico", "procedural_relation": "neutral"}}
      ]
    }}

    ---

    NARRATIVA REAL A CLASIFICAR (usa ÚNICAMENTE esta información):
    "{narrative}"

    Responde ahora con el JSON de la NARRATIVA REAL, no del ejemplo.
    '''

    result = await query_llm(system_prompt, user_prompt, FactsOnlySchema)
    return result["facts"]


# ── FASE 1b: SOLO evaluar elementos del tipo penal ──────────────────
async def _analizar_elementos(narrative: str, offense_name: str | None, offense_id: int,
                               elements: list, legal_context: list, legal_articles: list) -> list:
    system_prompt = '''
    Eres un asistente de auditoría jurídica para el Ministerio Público. Tu ÚNICA tarea es comparar
    la narrativa de hechos con cada elemento jurídico del tipo penal y decidir si cada elemento está
    acreditado, no acreditado o faltante. NO clasifiques hechos, NO hagas nada más que esto.

    REGLAS ESTRICTAS:
    1. No conviertas definiciones legales en hechos. Solo usa datos expresados en la narrativa.
    2. ACREDITADO solo si existe un hecho concreto y verificable en la narrativa que cubre el elemento.
    3. CONTRADICTORIO solo si la narrativa expresa explícitamente lo contrario del elemento.
    4. FALTANTE cuando la narrativa no aporta información suficiente, es ambigua u omite un requisito esencial.
    5. evidence_found debe ser una cita literal breve de la narrativa; nunca una definición legal.
    6. missing_reason debe ser específica de ESTE caso — nunca una frase genérica reutilizable en otro caso.
    7. PROHIBIDO: nunca repitas ni parafrasees las frases del EJEMPLO de abajo.
    8. Responde ÚNICAMENTE con JSON válido, sin texto adicional.
    '''

    user_prompt = f'''
    EJEMPLO DE FORMATO (caso ficticio de referencia — NO copies estos valores):

    Narrativa de ejemplo: "...el imputado tomó el teléfono celular marca Samsung de la mesa
    del comedor de la vivienda de la víctima, sin su permiso, mientras ella dormía..."

    {{
      "elements_analysis": [
        {{"element_id": 1, "status": "ACREDITADO", "evidence_found": "tomó el teléfono celular marca Samsung de la mesa del comedor", "missing_reason": null}},
        {{"element_id": 2, "status": "FALTANTE", "evidence_found": null, "missing_reason": "La narrativa no menciona quién es el propietario del teléfono ni si pertenece a persona distinta del imputado"}}
      ]
    }}

    ---

    CASO REAL A ANALIZAR (usa ÚNICAMENTE esta información):

    HECHOS DE LA CARPETA:
    "{narrative}"

    DELITO:
    {offense_name or f"ID {offense_id}"}

    MARCO JURÍDICO RECUPERADO DE QDRANT:
    {json.dumps(legal_context, ensure_ascii=False)}

    ELEMENTOS A EVALUAR:
    {json.dumps(elements, ensure_ascii=False)}

    ARTÍCULOS ASOCIADOS:
    {json.dumps(legal_articles, ensure_ascii=False)}

    Responde ahora con el JSON del CASO REAL, no del ejemplo.
    '''

    result = await query_llm(system_prompt, user_prompt, ElementsAnalysisSchema)
    return result["elements_analysis"]


# ── FASE 2: auditar el resultado de fase 1b + sugerir diligencias ──
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
    4. Si TODOS los elementos ya están ACREDITADOS, suggested_diligences puede quedar vacío.
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


# ── Orquestador: llama a las tres fases en secuencia ────────────────
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

    facts = await _clasificar_hechos(narrative)

    elements_analysis = await _analizar_elementos(
        narrative, offense_name, offense_id, elements, legal_context, legal_articles
    )

    audit_result = await _auditar_objetividad(
        narrative, offense_name, offense_id, elements_analysis, legal_context
    )

    return {
        "facts": facts,
        "elements_analysis": elements_analysis,
        "objectivity_audit": audit_result["objectivity_audit"],
        "suggested_diligences": audit_result["suggested_diligences"],
    }