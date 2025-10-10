from hashlib import sha256

def get_password_hash(p: str) -> str:
    return sha256(p.encode()).hexdigest()
