from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.core.config import settings

app = FastAPI(title=settings.app.name, version=settings.app.version)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/config")
def get_config():
    return {
        "app": {"name": settings.app.name, "version": settings.app.version},
        "modules": settings.modules.model_dump(),
    }

# Conditional routers
if settings.modules.devices:
    from app.modules.devices.routes import router as devices_router
    app.include_router(devices_router)

if settings.modules.diagrams:
    from app.modules.diagrams.routes import router as diagrams_router
    app.include_router(diagrams_router)

if settings.modules.alerts:
    from app.modules.alerts.routes import router as alerts_router
    app.include_router(alerts_router)

if settings.modules.metrics:
    from app.modules.metrics.routes import router as metrics_router
    app.include_router(metrics_router)
    from app.modules.metrics.ws import router as metrics_ws_router
    app.include_router(metrics_ws_router)

if settings.modules.dashboard:
    from app.modules.dashboard.ws import router as dashboard_ws_router
    app.include_router(dashboard_ws_router)

if settings.modules.reports:
    from app.modules.reports.routes import router as reports_router
    app.include_router(reports_router)
