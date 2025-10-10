from pydantic import BaseModel
from typing import Optional, List
from datetime import datetime

class PortBase(BaseModel):
    id: int
    port_index: int
    name: Optional[str] = None
    type: Optional[str] = None
    description: Optional[str] = None
    vlan_id: Optional[int] = None
    tagged: Optional[bool] = None
    admin_status: Optional[bool] = None
    class Config: from_attributes = True

class DeviceBase(BaseModel):
    id: int
    type: str
    name: str
    brand: Optional[str] = None
    model: Optional[str] = None
    serial_number: Optional[str] = None
    location: Optional[str] = None
    idf: Optional[str] = None
    rack_position: Optional[str] = None
    total_ports: Optional[int] = None
    os: Optional[str] = None
    server_type: Optional[str] = None
    notes: Optional[str] = None
    created_at: datetime
    updated_at: datetime
    class Config: from_attributes = True

class DeviceWithPorts(DeviceBase):
    ports: List[PortBase] = []
