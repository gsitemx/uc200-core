from sqlalchemy import Column, String, Integer, Text, DateTime, CheckConstraint
from sqlalchemy.sql import func
from sqlalchemy.orm import relationship
from app.db.base import Base

class Device(Base):
    __tablename__ = "devices"

    id = Column(Integer, primary_key=True, index=True)
    type = Column(String(20), nullable=False)
    name = Column(String(100), nullable=False)
    brand = Column(String(100))
    model = Column(String(100))
    serial_number = Column(String(100))
    location = Column(String(200))  # store host/IP if desired
    idf = Column(String(50))
    rack_position = Column(String(50))
    total_ports = Column(Integer)
    os = Column(String(100))
    server_type = Column(String(20))
    notes = Column(Text)
    created_at = Column(DateTime, server_default=func.now())
    updated_at = Column(DateTime, server_default=func.now(), onupdate=func.now())

    ports = relationship("Port", back_populates="device", cascade="all, delete-orphan")

    __table_args__ = (
        CheckConstraint("type IN ('switch','router','firewall','server')"),
    )
