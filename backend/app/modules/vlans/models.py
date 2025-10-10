from sqlalchemy import Column, String, Integer, Text
from app.db.base import Base

class Vlan(Base):
    __tablename__ = "vlans"

    id = Column(Integer, primary_key=True)
    site = Column(String(100))
    vlan_number = Column(Integer, nullable=False)
    name = Column(String(100))
    description = Column(Text)
