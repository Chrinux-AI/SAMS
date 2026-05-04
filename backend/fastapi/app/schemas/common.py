from typing import Any

from pydantic import BaseModel, ConfigDict


class APIResponse(BaseModel):
    success: bool = True
    message: str
    data: Any = {}
    meta: dict[str, Any] = {}


class ORMBaseSchema(BaseModel):
    model_config = ConfigDict(from_attributes=True)
