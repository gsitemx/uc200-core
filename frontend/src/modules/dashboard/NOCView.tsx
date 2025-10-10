import React from "react";
import { useNOCWS } from "./useNOCWS";

const NOCView: React.FC = () => {
  const devices = useNOCWS();
  const colors: Record<string, string> = { ok: "#2ecc71", warning: "#f1c40f", critical: "#e74c3c" };

  return (
    <div>
      <h2>Vista NOC</h2>
      <div style={{display:'grid', gridTemplateColumns:'repeat(auto-fill, minmax(200px, 1fr))', gap:16}}>
        {devices.map(d => (
          <div key={d.id} style={{background: colors[d.status], color:'#fff', padding:16, borderRadius:8}}>
            <h3 style={{margin:0}}>{d.name}</h3>
            <p style={{margin:'8px 0 0'}}>{d.type.toUpperCase()}</p>
            <small>Estado: {d.status.toUpperCase()}</small>
          </div>
        ))}
      </div>
    </div>
  );
};
export default NOCView;
