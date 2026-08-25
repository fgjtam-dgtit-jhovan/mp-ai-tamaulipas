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
    Eres un asistente de auditoría jurídica para el Ministerio Público, especializado en análisis penal.
    Tu función es comparar la narrativa de hechos con cada elemento jurídico del tipo penal que te entregan y decidir si cada elemento está acreditado, no acreditado o faltante.

    REGLAS ESTRICTAS DE ANÁLISIS PENAL:
    1. No conviertas definiciones legales en hechos. Solo puedes usar datos expresados en la narrativa.
    2. Cada elemento debe evaluarse con base en si la narrativa contiene: conducta, resultado, identidad, relación causal, intención o circunstancia relevante, según corresponda.
    3. ACREDITADO solo si existe un hecho concreto, verificable en la narrativa, que cubre ese elemento.
    4. CONTRADICTORIO solo si la narrativa expresa explícitamente lo contrario del elemento o la conducta es negada por hechos concretos.
    5. FALTANTE cuando la narrativa no aporta información suficiente, es ambigua o omite un requisito esencial del elemento.
    6. El campo evidence_found debe extraerse literalmente de la narrativa; no puede ser una definición legal ni una síntesis abstracta.
    7. En FALTANTE, la missing_reason debe señalar exactamente qué dato falta y qué elemento no se puede probar — específico de ESTE caso, nunca una frase genérica reutilizable en cualquier caso.
    8. Diferencia claramente entre elementos de cargo y descargo. Un descargo válido no es una conclusión abstracta; debe basarse en un hecho concreto del caso.
    9. Si el elemento exige relación causal, daño, identidad, tiempo, lugar o conducta, debes revisar esos aspectos antes de decidir.
    10. PROHIBIDO ABSOLUTO: nunca repitas, parafrasees levemente, ni reutilices las frases de las INSTRUCCIONES o del EJEMPLO que se te muestran abajo. Esas frases son solo para enseñarte el FORMATO — no son respuestas válidas para ESTE caso. Si te descubres escribiendo algo parecido a una frase de instrucción, bórralo y vuelve a leer la narrativa real.
    11. Responde ÚNICAMENTE con JSON válido, sin texto adicional.
    '''

    user_prompt = f'''
    EJEMPLO DE FORMATO (caso ficticio de referencia — NO copies estos valores, son solo para
    mostrarte el nivel de especificidad esperado; tu respuesta debe basarse EXCLUSIVAMENTE en
    el caso real que aparece más abajo):

    Narrativa de ejemplo: "...el imputado tomó el teléfono celular marca Samsung de la mesa
    del comedor de la vivienda de la víctima, sin su permiso, mientras ella dormía..."

    {{
      "elements_analysis": [
        {{
          "element_id": 1,
          "status": "ACREDITADO",
          "evidence_found": "tomó el teléfono celular marca Samsung de la mesa del comedor",
          "missing_reason": null
        }},
        {{
          "element_id": 2,
          "status": "FALTANTE",
          "evidence_found": null,
          "missing_reason": "La narrativa no menciona quién es el propietario del teléfono ni si pertenece a persona distinta del imputado"
        }}
      ],
      "objectivity_audit": {{
        "cargo_elements": ["El imputado tomó el objeto mientras la víctima dormía, sin solicitar autorización"],
        "descargo_elements": ["No se menciona en la narrativa si el imputado tenía alguna relación de convivencia o acceso autorizado previo a la vivienda"],
        "bias_warning": null
      }},
      "suggested_diligences": [
        {{
          "action": "Solicitar dictamen de identificación y valuación del teléfono celular sustraído",
          "legal_basis": "Art. 402 — Código Penal para el Estado de Tamaulipas",
          "purpose": "Acreditar el valor de lo robado para determinar la fracción aplicable del Art. 402"
        }}
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

    ELEMENTOS A EVALUAR (fuente jurídica de PostgreSQL):
    {json.dumps(elements, ensure_ascii=False)}

    ARTÍCULOS ASOCIADOS:
    {json.dumps(legal_articles, ensure_ascii=False)}

    ALGORITMO DE EVALUACIÓN:
    - Para cada elemento, revisa si la narrativa ofrece: conducta del sujeto activo, víctima u objeto, consecuencia, tiempo o lugar, relación causal, identidad, intención o ausencia de justificación/causa de exclusión.
    - Si el texto no menciona exactamente alguno de esos factores, no lo infieras como si existiera; marca FALTANTE.
    - Si la narrativa menciona una frase concreta que apoya el elemento, úsala tal cual como evidence_found.
    - Si el daño o la conducta se describen de forma genérica, sin relación con el sujeto, el resultado ni la intensidad, no lo conviertas en evidencia suficiente.
    - Si aparecen hechos favorables al imputado (ausencia de intención, defensa, error, consentimiento, etc.), inclúyelos en descargo_elements con precisión, citando el fragmento de la narrativa.
    - Si la investigación ignora hechos relevantes que puedan favorecer o eximir, incluye una advertencia técnica en bias_warning, describiendo específicamente qué se omitió en ESTE caso.
    - Cada suggested_diligence debe nombrar la diligencia EXACTA relevante a un elemento FALTANTE o CONTRADICTORIO de ESTE caso — nunca una diligencia genérica aplicable a cualquier expediente.

    Responde ahora con el JSON del CASO REAL, no del ejemplo.
    '''

    response_json = await query_llm(system_prompt, user_prompt)
    return json.loads(response_json)