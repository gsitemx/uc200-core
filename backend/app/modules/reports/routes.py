from fastapi import APIRouter, Depends, Response
from sqlalchemy.orm import Session
from datetime import datetime, timedelta
from app.db.session import get_db
from app.modules.checks.models import Check
import csv, io
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet

router = APIRouter(tags=["Reports"])

def get_checks(db: Session, device_id: int, period: str):
    now = datetime.utcnow()
    ranges = {"day": now - timedelta(days=1), "week": now - timedelta(days=7), "month": now - timedelta(days=30)}
    since = ranges.get(period, ranges["day"])
    return (db.query(Check)
            .filter(Check.device_id == device_id, Check.last_checked >= since)
            .order_by(Check.last_checked.asc()).all())

@router.get("/reports/{device_id}/export/csv")
def export_csv(device_id: int, period: str = "day", db: Session = Depends(get_db)):
    checks = get_checks(db, device_id, period)
    buf = io.StringIO(); w = csv.writer(buf)
    w.writerow(["Time", "Type", "Status", "Value"])
    for c in checks:
        w.writerow([c.last_checked, c.check_type, c.status, c.last_value])
    resp = Response(content=buf.getvalue(), media_type="text/csv")
    resp.headers["Content-Disposition"] = f"attachment; filename=report_{device_id}_{period}.csv"
    return resp

@router.get("/reports/{device_id}/export/pdf")
def export_pdf(device_id: int, period: str = "day", db: Session = Depends(get_db)):
    checks = get_checks(db, device_id, period)
    buf = io.BytesIO()
    doc = SimpleDocTemplate(buf, pagesize=A4)
    styles = getSampleStyleSheet()
    elements = [Paragraph(f"Reporte de métricas – Dispositivo {device_id}", styles["Title"]),
                Paragraph(f"Periodo: {period}", styles["Normal"])]
    data = [["Time", "Type", "Status", "Value"]]
    for c in checks:
        data.append([str(c.last_checked), c.check_type, c.status, str(c.last_value)])
    table = Table(data, colWidths=[120, 80, 80, 200])
    table.setStyle(TableStyle([("BACKGROUND", (0,0), (-1,0), colors.HexColor("#2c3e50")),
                               ("TEXTCOLOR", (0,0), (-1,0), colors.white),
                               ("GRID", (0,0), (-1,-1), 0.5, colors.grey),
                               ("FONTNAME", (0,0), (-1,0), "Helvetica-Bold"),
                               ("ALIGN", (0,0), (-1,-1), "CENTER")]))
    elements.append(table); doc.build(elements); buf.seek(0)
    resp = Response(content=buf.read(), media_type="application/pdf")
    resp.headers["Content-Disposition"] = f"attachment; filename=report_{device_id}_{period}.pdf"
    return resp
