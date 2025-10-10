# Network Monitoring System

Levanta con Docker Compose y accede a:
- API: http://localhost:8000/docs
- Frontend: http://localhost:3000
- pgAdmin: http://localhost:5050

## Pasos
1) `docker-compose up --build`
2) Ejecuta migraciones:
```
docker-compose exec backend alembic upgrade head
```
3) (Opcional) Inserta datos de prueba con tus propios INSERTs.
