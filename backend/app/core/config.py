import yaml
from pydantic import BaseModel

class AppConfig(BaseModel):
    name: str
    version: str

class ModulesConfig(BaseModel):
    devices: bool = True
    ports: bool = True
    vlans: bool = True
    diagrams: bool = True
    alerts: bool = True
    monitoring: bool = True
    metrics: bool = True
    dashboard: bool = True
    reports: bool = True
    notifications: bool = False

class DatabaseConfig(BaseModel):
    url: str

class RedisConfig(BaseModel):
    url: str

class Settings(BaseModel):
    app: AppConfig
    modules: ModulesConfig
    database: DatabaseConfig
    redis: RedisConfig

def load_settings(path: str = "app/settings.yaml") -> Settings:
    with open(path, "r", encoding="utf-8") as f:
        data = yaml.safe_load(f)
    return Settings(**data)

settings = load_settings()
