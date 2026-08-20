import json

async def query_llm(system_prompt: str, user_prompt: str) -> str:
    # Simulador de respuesta estructurada de Ollama/LLM
    mock_response = {
        "elements_analysis": [
            {
                "element_id": 1,
                "status": "ACREDITADO",
                "evidence_found": "El sujeto tomó la computadora portátil del escritorio del denunciante.",
                "missing_reason": None
            },
            {
                "element_id": 2,
                "status": "ACREDITADO",
                "evidence_found": "Una computadora portátil HP negra.",
                "missing_reason": None
            },
            {
                "element_id": 3,
                "status": "ACREDITADO",
                "evidence_found": "Factura número 45892 a nombre de la víctima.",
                "missing_reason": None
            },
            {
                "element_id": 4,
                "status": "FALTANTE",
                "evidence_found": None,
                "missing_reason": "No consta en el expediente la entrevista formal de la víctima declarando la falta de autorización."
            }
        ],
        "objectivity_audit": {
            "cargo_elements": ["Testigo señala ver al imputado salir con el equipo."],
            "descargo_elements": ["El imputado afirma que el equipo le fue prestado temporalmente."],
            "bias_warning": "Se debe corroborar si existía un acuerdo previo de préstamo antes de determinar la intención de apoderamiento."
        },
        "suggested_diligences": [
            {
                "action": "Recabar entrevista ampliatoria de la víctima respecto al consentimiento.",
                "legal_basis": "Art. 399 Código Penal",
                "purpose": "Acreditar el elemento subjetivo de falta de consentimiento."
            }
        ]
    }
    return json.dumps(mock_response)