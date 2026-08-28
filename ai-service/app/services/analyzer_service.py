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
    - MANIFESTACION: la percepción, interpretación o afirmación SUBJETIVA de una persona sobre lo que
      pasó (ej. "yo iba caminando cuando...", "escuché un disparo", "creo que fue él").
    - EVIDENCIA: cualquier objeto físico, rastro material, documento, fotografía, video o registro
      mencionado — AUNQUE se conozca a través del relato de alguien. La prueba es: ¿describe un objeto
      o rastro que podría exhibirse o peritarse? Ejemplos que SIEMPRE son EVIDENCIA sin importar quién
      lo menciona: manchas de sangre, casquillos, huellas, impactos de bala en superficies, armas,
      prendas, fotografías, videos, documentos.
    - TESTIMONIO: declaración atribuida a un tercero distinto del declarante principal.
    - DATO_TECNICO: dictamen, medición, resultado o informe técnico/pericial.
    - HIPOTESIS: posibilidad o explicación no confirmada ("podría tratarse de...").
    - CONCLUSION: afirmación presentada como resultado o comprobación ("se comprobó que...").

    REGLA CLAVE PARA NO CONFUNDIR MANIFESTACION CON EVIDENCIA:
    El origen del dato (quién lo dice) NO determina la clasificación — lo que importa es LA NATURALEZA
    de lo descrito. Si el denunciante describe QUE VIO manchas de sangre y casquillos, ese fragmento se
    clasifica como EVIDENCIA (porque describe objetos materiales), no como MANIFESTACION, aunque toda la
    narrativa provenga de su declaración. Reserva MANIFESTACION para la parte subjetiva/interpretativa
    del relato (lo que la persona cree, siente, o afirma que ocurrió sin describir un objeto concreto).

    REGLAS ESTRICTAS:
    1. Extrae SOLO fragmentos que aparecen literalmente en la narrativa. No agregues información.
    2. No conviertas una manifestación en un hecho probado ni en conclusión.
    3. Si la narrativa no tiene fragmentos de una clasificación, simplemente no la incluyas.
    4. Máximo 8 hechos — prioriza los más relevantes para el caso.
    5. is_confirmed debe ser false si el propio texto trae señales de incertidumbre ("por confirmar",
       "presuntamente", "se reporta", "sin confirmar", "aparentemente" o similares) — revisa el fragmento
       completo, no solo el inicio.
    6. PROHIBIDO: nunca repitas ni parafrasees las frases del EJEMPLO de abajo.
    7. Responde ÚNICAMENTE con JSON válido.
    '''

    user_prompt = f'''
    EJEMPLO DE FORMATO (caso ficticio de referencia — NO copies estos valores):

    Narrativa de ejemplo: "...el denunciante manifestó que vio al imputado tomar el teléfono de
    la mesa. Un vecino declaró haber escuchado gritos. El denunciante señaló que se encuentran
    manchas de sangre en el piso de la cocina. Se anexa fotografía del lugar..."

    {{
      "facts": [
        {{"information_type": "MANIFESTACION", "content": "vio al imputado tomar el teléfono de la mesa", "source": "declaración del denunciante", "procedural_relation": "cargo", "is_confirmed": true}},
        {{"information_type": "TESTIMONIO", "content": "escuchó gritos", "source": "testimonio de vecino", "procedural_relation": "cargo", "is_confirmed": true}},
        {{"information_type": "EVIDENCIA", "content": "manchas de sangre en el piso de la cocina", "source": "declaración del denunciante", "procedural_relation": "cargo", "is_confirmed": true}},
        {{"information_type": "EVIDENCIA", "content": "fotografía del lugar de los hechos", "source": "anexo fotográfico", "procedural_relation": "neutral", "is_confirmed": true}}
      ]
    }}

    ---

    NARRATIVA REAL A CLASIFICAR (usa ÚNICAMENTE esta información):
    "{narrative}"

    Responde ahora con el JSON de la NARRATIVA REAL, no del ejemplo.
    '''

    result = await query_llm(system_prompt, user_prompt, FactsOnlySchema)

    facts = result["facts"]
    # Id estable asignado por Python (NO por el LLM), inmediatamente
    # después de recibir la respuesta. Todo lo que ocurra después en el
    # pipeline (filtrado, deduplicado, persistencia) referencia este id,
    # nunca la posición del array — evita el bug de desalineación cuando
    # Laravel elimina duplicados con ->unique()->values().
    for idx, fact in enumerate(facts):
        fact["id"] = f"f{idx}"

    return facts


# ── FASE 1b: SOLO evaluar elementos del tipo penal ──────────────────
async def _analizar_elementos(narrative: str, offense_name: str | None, offense_id: int,
                               elements: list, legal_context: list, legal_articles: list,
                               facts: list) -> list:
    system_prompt = '''
    Eres un asistente de auditoría jurídica para el Ministerio Público. Tu ÚNICA tarea es comparar
    la lista de hechos ya clasificados con cada elemento jurídico del tipo penal y decidir si cada
    elemento está acreditado, no acreditado o faltante. NO reclasifiques hechos, NO hagas nada más
    que esto.

    REGLAS ESTRICTAS:
    1. Solo puedes usar los hechos de la lista que se te entrega — no regreses a la narrativa cruda.
    2. ACREDITADO solo si existe un hecho concreto en esa lista que cubre el elemento.
    3. CONTRADICTORIO solo si algún hecho de la lista expresa explícitamente lo contrario del elemento.
    4. FALTANTE cuando ningún hecho de la lista cubre el elemento.
    5. evidence_found debe ser el texto EXACTO del hecho de la lista que usaste; nunca una definición legal.
    6. supporting_fact_id debe ser el valor EXACTO del campo "id" (ej. "f0", "f1") del hecho de la
       lista que usaste. Cada hecho en la lista trae su propio "id" — cópialo tal cual, nunca inventes
       uno nuevo. Si status es FALTANTE, deja supporting_fact_id en null.
    7. missing_reason debe ser específica de ESTE caso — nunca una frase genérica reutilizable en otro caso.
    8. Cada hecho de la lista trae un campo is_confirmed. Si is_confirmed es false, NO lo uses para
       marcar ACREDITADO — márcalo FALTANTE y explica en missing_reason que la información disponible
       está pendiente de confirmación.
    9. No reutilices el mismo hecho como evidence_found para dos elementos distintos a menos que ese
       hecho realmente contenga información específica para AMBOS por separado. Si dos elementos solo
       tienen en común un hecho genérico o incierto, revisa con más cuidado si de verdad aplica a cada
       uno o si en realidad falta información específica para alguno.
    10. IMPORTANTE: revisa TODOS los hechos de la lista contra CADA elemento, uno por uno — no te
        detengas en el primer hecho que parezca relevante ni ignores hechos que están más abajo en la
        lista. Un hecho que aparece a mitad o al final de la lista puede ser el que sustenta un elemento.
    11. PROHIBIDO ABSOLUTO: nunca escribas la palabra "FACTS", "lista de hechos", ni ningún nombre de
        variable o etiqueta técnica dentro de evidence_found o missing_reason — esos campos deben leerse
        como una oración normal sobre EL CASO, nunca mencionar la estructura de datos que estás usando.
    12. PROHIBIDO: nunca repitas ni parafrasees las frases del EJEMPLO de abajo.
    13. Responde ÚNICAMENTE con JSON válido, sin texto adicional.
    '''

    user_prompt = f'''
    EJEMPLO DE FORMATO (caso ficticio de referencia — NO copies estos valores):

    Hechos disponibles para este caso ficticio:
    [
      {{"id": "f0", "information_type": "MANIFESTACION", "content": "tomó el teléfono celular marca Samsung de la mesa del comedor"}},
      {{"id": "f1", "information_type": "MANIFESTACION", "content": "ocurrió mientras la víctima dormía"}}
    ]

    {{
      "elements_analysis": [
        {{"element_id": 1, "status": "ACREDITADO", "evidence_found": "tomó el teléfono celular marca Samsung de la mesa del comedor", "missing_reason": null, "supporting_fact_id": "f0"}},
        {{"element_id": 2, "status": "FALTANTE", "evidence_found": null, "missing_reason": "No se menciona quién es el propietario del teléfono", "supporting_fact_id": null}}
      ]
    }}

    ---

    CASO REAL A ANALIZAR (usa ÚNICAMENTE los hechos listados abajo, no la narrativa cruda):

    DELITO:
    {offense_name or f"ID {offense_id}"}

    HECHOS DISPONIBLES (usa el campo "id" de cada hecho para supporting_fact_id):
    {json.dumps(facts, ensure_ascii=False)}

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
    fact_date: str | None = None,
):
    if not elements:
        raise ValueError(f'El delito {offense_id} no tiene elementos jurídicos configurados.')

    legal_context = await search_legal_articles(query=narrative, offense_id=offense_id, limit=5, as_of_date=fact_date)

    facts = await _clasificar_hechos(narrative)

    elements_analysis = await _analizar_elementos(
        narrative, offense_name, offense_id, elements, legal_context, legal_articles, facts
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