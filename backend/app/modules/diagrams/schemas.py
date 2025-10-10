from pydantic import BaseModel
from typing import Any
from uuid import UUID
from datetime import datetime

class DiagramBase(BaseModel):
    name: str
    json_data: Any

class DiagramCreate(DiagramBase):
    pass

class DiagramResponse(DiagramBase):
    id: UUID
    created_at: datetime
    updated_at: datetime
    class Config:
        from_attributes = True
