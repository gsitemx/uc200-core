import React, { useEffect, useState } from "react";
import DiagramCanvasLive from "./DiagramCanvasLive";

const TopologyPage: React.FC = () => {
  const [diagram, setDiagram] = useState<any|null>(null);
  useEffect(()=>{
    fetch("http://localhost:8000/diagrams").then(r=>r.json()).then(d=>setDiagram(d[0]));
  },[]);
  return <div>{diagram ? <DiagramCanvasLive diagram={diagram} /> : <p>Cargando diagrama...</p>}</div>;
};
export default TopologyPage;
