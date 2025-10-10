from sqlalchemy import Column, Integer, String, DateTime
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.sql import func
from app.db.base import Base

class Check(Base):
    __tablename__ = "checks"
    id = Column(Integer, primary_key=True)
    device_id = Column(Integer, nullable=False)
    check_type = Column(String(50), nullable=False)  # cpu, memory, disk, bandwidth_in, bandwidth_out, icmp, etc.
    status = Column(String(20), nullable=False)      # ok, warning, critical
    last_checked = Column(DateTime, server_default=func.now())
    last_value = Column(JSONB)
