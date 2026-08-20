async def search_legal_articles(query: str, limit: int = 3):
    # Mock temporal de respuesta de Qdrant
    return [
        "Art. 399 Código Penal: Comete el delito de robo el que se apodera de una cosa mueble ajena sin derecho y sin consentimiento.",
        "Art. 400 Código Penal: Sanciones aplicables al delito de robo simple."
    ]