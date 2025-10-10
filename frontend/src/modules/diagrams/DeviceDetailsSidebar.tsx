import React from "react";
const DeviceDetailsSidebar: React.FC<{deviceId: number|null, onClose: ()=>void}> = ({deviceId, onClose}) => {
  if (!deviceId) return null;
  return (
    <div style={{position:'fixed', right:0, top:0, width:400, height:'100%', background:'#fff', boxShadow:'-3px 0 8px rgba(0,0,0,.2)', padding:16}}>
      <button onClick={onClose} style={{float:'right'}}>Cerrar</button>
      <h3>Detalles de dispositivo #{deviceId}</h3>
      {/* Aquí se renderiza info del endpoint /devices/{id}/details */}
    </div>
  );
};
export default DeviceDetailsSidebar;
