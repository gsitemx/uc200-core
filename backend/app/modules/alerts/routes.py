from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from datetime import datetime
from app.db.session import get_db
from . import models, schemas

router = APIRouter(tags=["Alerts"])

@router.get("/alerts", response_model=list[schemas.AlertResponse])
def list_alerts(db: Session = Depends(get_db)):
    return db.query(models.Alert).order_by(models.Alert.created_at.desc()).all()

@router.get("/alerts/{alert_id}", response_model=schemas.AlertResponse)
def get_alert(alert_id: int, db: Session = Depends(get_db)):
    alert = db.query(models.Alert).filter(models.Alert.id == alert_id).first()
    if not alert:
        raise HTTPException(status_code=404, detail="Alerta no encontrada")
    return alert

@router.post("/alerts", response_model=schemas.AlertResponse)
def create_alert(payload: schemas.AlertCreate, db: Session = Depends(get_db)):
    alert = models.Alert(device_id=payload.device_id, severity=payload.severity, message=payload.message)
    db.add(alert); db.commit(); db.refresh(alert)
    return alert

@router.patch("/alerts/{alert_id}/ack", response_model=schemas.AlertResponse)
def acknowledge_alert(alert_id: int, db: Session = Depends(get_db)):
    alert = db.query(models.Alert).filter(models.Alert.id == alert_id).first()
    if not alert:
        raise HTTPException(status_code=404, detail="Alerta no encontrada")
    alert.acknowledged_by = "admin"
    alert.acknowledged_at = datetime.utcnow()
    db.commit(); db.refresh(alert)
    return alert
