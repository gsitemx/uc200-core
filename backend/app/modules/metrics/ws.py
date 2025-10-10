from fastapi import APIRouter, WebSocket, WebSocketDisconnect, Depends
from sqlalchemy.orm import Session
from datetime import datetime, timedelta
import asyncio, json
from app.db.session import get_db
from app.modules.checks.models import Check

router = APIRouter(tags=["MetricsWS"])

@router.websocket("/ws/metrics/{device_id}")
async def metrics_ws(websocket: WebSocket, device_id: int, db: Session = Depends(get_db)):
    await websocket.accept()
    try:
        while True:
            since = datetime.utcnow() - timedelta(minutes=10)
            checks = (db.query(Check)
                      .filter(Check.device_id == device_id, Check.last_checked >= since)
                      .order_by(Check.last_checked.desc()).limit(50).all())
            payload = [{
                "time": c.last_checked.isoformat(),
                "type": c.check_type,
                "status": c.status,
                "value": c.last_value,
            } for c in checks]
            await websocket.send_text(json.dumps(payload))
            await asyncio.sleep(10)
    except WebSocketDisconnect:
        pass
