from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

revision = "20250925_init"
down_revision = None
branch_labels = None
depends_on = None

def upgrade():
    op.create_table("devices",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("type", sa.String(20), nullable=False),
        sa.Column("name", sa.String(100), nullable=False),
        sa.Column("brand", sa.String(100)),
        sa.Column("model", sa.String(100)),
        sa.Column("serial_number", sa.String(100)),
        sa.Column("location", sa.String(200)),
        sa.Column("idf", sa.String(50)),
        sa.Column("rack_position", sa.String(50)),
        sa.Column("total_ports", sa.Integer),
        sa.Column("os", sa.String(100)),
        sa.Column("server_type", sa.String(20)),
        sa.Column("notes", sa.Text),
        sa.Column("created_at", sa.DateTime, server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime, server_default=sa.func.now()),
        sa.CheckConstraint("type IN ('switch','router','firewall','server')"),
    )

    op.create_table("vlans",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("site", sa.String(100)),
        sa.Column("vlan_number", sa.Integer, nullable=False),
        sa.Column("name", sa.String(100)),
        sa.Column("description", sa.Text),
        sa.CheckConstraint("vlan_number BETWEEN 1 AND 4094"),
    )

    op.create_table("ports",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("device_id", sa.Integer, sa.ForeignKey("devices.id", ondelete="CASCADE"), nullable=False),
        sa.Column("port_index", sa.Integer, nullable=False),
        sa.Column("name", sa.String(50)),
        sa.Column("type", sa.String(20)),
        sa.Column("description", sa.String(200)),
        sa.Column("vlan_id", sa.Integer, sa.ForeignKey("vlans.id")),
        sa.Column("tagged", sa.Boolean, server_default=sa.text("false")),
        sa.Column("connected_device_id", sa.Integer, sa.ForeignKey("devices.id")),
        sa.Column("connected_port_id", sa.Integer),
        sa.Column("admin_status", sa.Boolean, server_default=sa.text("true")),
        sa.Column("created_at", sa.DateTime, server_default=sa.func.now()),
        sa.UniqueConstraint("device_id", "port_index", name="uq_device_port"),
    )

    op.create_table("diagrams",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("gen_random_uuid()")),
        sa.Column("name", sa.String(100), nullable=False),
        sa.Column("json_data", postgresql.JSONB, nullable=False),
        sa.Column("created_at", sa.DateTime, server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime, server_default=sa.func.now()),
    )

    op.create_table("alerts",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("device_id", sa.Integer, sa.ForeignKey("devices.id", ondelete="CASCADE")),
        sa.Column("severity", sa.String(20), nullable=False),
        sa.Column("message", sa.Text, nullable=False),
        sa.Column("created_at", sa.DateTime, server_default=sa.func.now()),
        sa.Column("acknowledged_by", sa.String(100)),
        sa.Column("acknowledged_at", sa.DateTime),
        sa.CheckConstraint("severity IN ('info','warning','critical')"),
    )

    op.create_table("checks",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("device_id", sa.Integer, nullable=False),
        sa.Column("check_type", sa.String(50), nullable=False),
        sa.Column("status", sa.String(20), nullable=False),
        sa.Column("last_checked", sa.DateTime, server_default=sa.func.now()),
        sa.Column("last_value", postgresql.JSONB),
        sa.CheckConstraint("status IN ('ok','warning','critical')"),
    )

def downgrade():
    op.drop_table("checks")
    op.drop_table("alerts")
    op.drop_table("diagrams")
    op.drop_table("ports")
    op.drop_table("vlans")
    op.drop_table("devices")
