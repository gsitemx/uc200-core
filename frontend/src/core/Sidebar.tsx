import React from "react";
const Sidebar: React.FC = () => (
  <div style={{ width: 220, background: "#f5f5f5", padding: 16 }}>
    <h3>Menú</h3>
    <ul style={{ listStyle: 'none', padding: 0 }}>
      <li><a href="#">Dashboard</a></li>
      <li><a href="#">Dispositivos</a></li>
      <li><a href="#">Diagramas</a></li>
      <li><a href="#">Reportes</a></li>
      <li><a href="#">Alertas</a></li>
    </ul>
  </div>
);
export default Sidebar;
