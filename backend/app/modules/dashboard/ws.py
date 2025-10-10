from fastapi import APIRouter, WebSocket, WebSocketDisconnect, Depends
from sqlalchemy.orm import Session
from sqlalchemy import func
import asyncio, json
from datetime import datetime
from app.db.session import get_db
from app.modules.devices.models import Device
from app.modules.alerts.models import Alert

router = APIRouter(tags=["NOCWS"])

@router.websocket("/ws/noc")
async def noc_ws(websocket: WebSocket, db: Session = Depends(get_db)):
    await websocket.accept()
    try:
        while True:
            devices = db.query(Device).all()
            data = []
            for d in devices:
                active = (db.query(Alert)
                          .filter(Alert.device_id == d.id, Alert.acknowledged_at == None)
                          .order_by(Alert.created_at.desc()).first())
                status = "ok"; severity = None
                if active:
                    status = "critical" if active.severity == "critical" else "warning"
                    severity = active.severity
                data.append({
                    "id": d.id, "name": d.name, "type": d.type,
                    "status": status, "severity": severity,
                    "last_checked": datetime.utcnow().isoformat()
                })
            await websocket.send_text(json.dumps(data))
            await asyncio.sleep(5)
    except WebSocketDisconnect:
        pass
