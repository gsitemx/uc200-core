from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from app.db.session import get_db
from . import models, schemas

router = APIRouter(tags=["Diagrams"])

@router.get("/diagrams", response_model=list[schemas.DiagramResponse])
def list_diagrams(db: Session = Depends(get_db)):
    return db.query(models.Diagram).all()

@router.post("/diagrams", response_model=schemas.DiagramResponse)
def create_diagram(payload: schemas.DiagramCreate, db: Session = Depends(get_db)):
    d = models.Diagram(name=payload.name, json_data=payload.json_data)
    db.add(d); db.commit(); db.refresh(d); return d

@router.get("/diagrams/{diagram_id}", response_model=schemas.DiagramResponse)
def get_diagram(diagram_id: str, db: Session = Depends(get_db)):
    d = db.query(models.Diagram).filter(models.Diagram.id == diagram_id).first()
    if not d: raise HTTPException(status_code=404, detail="Diagrama no encontrado")
    return d
