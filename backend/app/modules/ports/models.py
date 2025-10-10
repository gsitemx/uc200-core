from sqlalchemy import Column, String, Integer, Boolean, DateTime, ForeignKey
from sqlalchemy.sql import func
from sqlalchemy.orm import relationship
from app.db.base import Base

class Port(Base):
    __tablename__ = "ports"

    id = Column(Integer, primary_key=True, index=True)
    device_id = Column(Integer, ForeignKey("devices.id", ondelete="CASCADE"), nullable=False)
    port_index = Column(Integer, nullable=False)
    name = Column(String(50))
    type = Column(String(20))  # access, trunk, wan, lan, mgmt
    description = Column(String(200))
    vlan_id = Column(Integer, ForeignKey("vlans.id"))
    tagged = Column(Boolean, default=False)
    connected_device_id = Column(Integer, ForeignKey("devices.id"))
    connected_port_id = Column(Integer)
    admin_status = Column(Boolean, default=True)
    created_at = Column(DateTime, server_default=func.now())

    device = relationship("Device", back_populates="ports")
