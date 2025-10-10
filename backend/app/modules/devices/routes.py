from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from app.db.session import get_db
from . import models, schemas
from app.modules.alerts.models import Alert

router = APIRouter(tags=["Devices"])

@router.get("/devices", response_model=list[schemas.DeviceWithPorts])
def list_devices(db: Session = Depends(get_db)):
    return db.query(models.Device).all()

@router.get("/devices/{device_id}", response_model=schemas.DeviceWithPorts)
def get_device(device_id: int, db: Session = Depends(get_db)):
    d = db.query(models.Device).filter(models.Device.id == device_id).first()
    if not d: raise HTTPException(status_code=404, detail="Dispositivo no encontrado")
    return d

@router.get("/devices/{device_id}/details")
def get_device_details(device_id: int, db: Session = Depends(get_db)):
    device = db.query(models.Device).filter(models.Device.id == device_id).first()
    if not device: raise HTTPException(status_code=404, detail="Dispositivo no encontrado")

    alerts = (db.query(Alert)
              .filter(Alert.device_id == device_id)
              .order_by(Alert.created_at.desc())
              .limit(3)
              .all())
    return {
        "id": device.id,
        "name": device.name,
        "type": device.type,
        "brand": device.brand,
        "model": device.model,
        "serial_number": device.serial_number,
        "location": device.location,
        "idf": device.idf,
        "rack_position": device.rack_position,
        "total_ports": device.total_ports,
        "os": device.os,
        "server_type": device.server_type,
        "ports": [
            {
                "id": p.id,
                "name": p.name,
                "type": p.type,
                "description": p.description,
                "vlan_id": p.vlan_id,
                "admin_status": p.admin_status,
            } for p in device.ports
        ],
        "alerts": [
            {
                "id": a.id,
                "severity": a.severity,
                "message": a.message,
                "created_at": a.created_at,
            } for a in alerts
        ],
    }
