from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session
from datetime import datetime, timedelta
from app.db.session import get_db
from app.modules.checks.models import Check

router = APIRouter(tags=["Metrics"])

@router.get("/metrics/{device_id}")
def get_device_metrics(device_id: int, minutes: int = 60, db: Session = Depends(get_db)):
    since = datetime.utcnow() - timedelta(minutes=minutes)
    checks = (db.query(Check)
              .filter(Check.device_id == device_id, Check.last_checked >= since)
              .order_by(Check.last_checked.asc())
              .all())
    return [{
        "time": c.last_checked, "type": c.check_type, "status": c.status, "value": c.last_value
    } for c in checks]

@router.get("/metrics/{device_id}/history")
def get_device_history(device_id: int, period: str = Query("day", pattern="^(day|week|month)$"), db: Session = Depends(get_db)):
    now = datetime.utcnow()
    ranges = {"day": now - timedelta(days=1), "week": now - timedelta(days=7), "month": now - timedelta(days=30)}
    since = ranges.get(period, ranges["day"])
    checks = (db.query(Check)
              .filter(Check.device_id == device_id, Check.last_checked >= since)
              .order_by(Check.last_checked.asc()).all())
    return [{
        "time": c.last_checked, "type": c.check_type, "status": c.status, "value": c.last_value
    } for c in checks]
