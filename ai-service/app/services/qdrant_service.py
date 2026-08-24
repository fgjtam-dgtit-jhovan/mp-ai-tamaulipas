import os
from typing import Any

import httpx
import psycopg2
from qdrant_client import AsyncQdrantClient, models

QDRANT_URL = os.getenv('QDRANT_URL', 'http://qdrant:6333')
QDRANT_COLLECTION = os.getenv('QDRANT_COLLECTION', 'legal_articles')
OLLAMA_URL = os.getenv('OLLAMA_URL', 'http://ollama:11434')
OLLAMA_EMBED_MODEL = os.getenv('OLLAMA_EMBED_MODEL', 'nomic-embed-text')


def _database_connection():
    return psycopg2.connect(
        host=os.getenv('AI_DB_HOST', os.getenv('DB_HOST', 'postgres')),
        port=os.getenv('AI_DB_PORT', os.getenv('DB_PORT', '5432')),
        dbname=os.getenv('POSTGRES_DB', os.getenv('DB_DATABASE', 'mpia_tamaulipas')),
        user=os.getenv('POSTGRES_USER', os.getenv('DB_USERNAME', 'mpia')),
        password=os.getenv('POSTGRES_PASSWORD', os.getenv('DB_PASSWORD', 'changeme')),
    )


async def _embed(texts: list[str]) -> list[list[float]]:
    async with httpx.AsyncClient(timeout=120.0) as client:
        response = await client.post(
            f'{OLLAMA_URL}/api/embed',
            json={'model': OLLAMA_EMBED_MODEL, 'input': texts},
        )
        response.raise_for_status()
        embeddings = response.json().get('embeddings')
        if not embeddings:
            raise RuntimeError('Ollama no devolvió embeddings para el texto jurídico.')
        return embeddings


def _articles_for_offense(offense_id: int) -> list[dict[str, Any]]:
    query = '''
        SELECT DISTINCT la.id, la.article_number, la.fraction, la.content, la.display_order,
               ld.title AS document_title, lv.version_label
        FROM offense_elements oe
        JOIN legal_articles la ON la.id = oe.legal_article_id
        JOIN legal_versions lv ON lv.id = la.legal_version_id
        JOIN legal_documents ld ON ld.id = lv.legal_document_id
        WHERE oe.external_offense_id = %s
          AND lv.effective_date <= CURRENT_DATE
          AND (lv.repealed_date IS NULL OR lv.repealed_date > CURRENT_DATE)
        ORDER BY la.display_order, la.article_number, la.fraction
    '''
    with _database_connection() as connection:
        with connection.cursor() as cursor:
            cursor.execute(query, (offense_id,))
            rows = cursor.fetchall()

    return [
        {
            'id': row[0],
            'article': row[1],
            'fraction': row[2],
            'content': row[3],
            'citation': f"Art. {row[1]}{', fracción ' + row[2] if row[2] else ''} — {row[4]} ({row[5]})",
        }
        for row in rows
    ]


async def search_legal_articles(query: str, offense_id: int, limit: int = 5) -> list[str]:
    articles = _articles_for_offense(offense_id)
    if not articles:
        raise RuntimeError(f'No hay artículos jurídicos vigentes configurados para el delito {offense_id}.')

    client = AsyncQdrantClient(url=QDRANT_URL)
    try:
        query_vector = (await _embed([query]))[0]
        collections = await client.get_collections()
        names = {collection.name for collection in collections.collections}
        if QDRANT_COLLECTION not in names:
            await client.create_collection(
                collection_name=QDRANT_COLLECTION,
                vectors_config=models.VectorParams(size=len(query_vector), distance=models.Distance.COSINE),
            )

        article_embeddings = await _embed([article['content'] for article in articles])
        await client.upsert(
            collection_name=QDRANT_COLLECTION,
            wait=True,
            points=[
                models.PointStruct(
                    id=article['id'],
                    vector=vector,
                    payload={**article, 'external_offense_id': offense_id},
                )
                for article, vector in zip(articles, article_embeddings)
            ],
        )

        results = await client.search(
            collection_name=QDRANT_COLLECTION,
            query_vector=query_vector,
            query_filter=models.Filter(
                must=[models.FieldCondition(key='external_offense_id', match=models.MatchValue(value=offense_id))]
            ),
            limit=limit,
        )
        return [point.payload.get('content', '') for point in results if point.payload]
    finally:
        await client.close()