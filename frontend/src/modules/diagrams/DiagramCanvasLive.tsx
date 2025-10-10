import React, { useEffect, useRef, useState } from "react";
import cytoscape, { Core } from "cytoscape";
import dagre from "cytoscape-dagre";
import { useNOCWS } from "../dashboard/useNOCWS";
import DeviceDetailsSidebar from "./DeviceDetailsSidebar";

cytoscape.use(dagre);

const DiagramCanvasLive: React.FC<{diagram:any}> = ({diagram}) => {
  const deviceStatuses = useNOCWS();
  const cyRef = useRef<Core|null>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const [selected, setSelected] = useState<number|null>(null);

  useEffect(()=>{
    if (!containerRef.current) return;
    if (cyRef.current) cyRef.current.destroy();
    const cy = cytoscape({
      container: containerRef.current,
      elements: [
        ...diagram.nodes.map((n:any)=>({
          data:{ id:n.id, label:n.label, device_id:n.device_id, type:n.type }, position:n.position
        })),
        ...diagram.links.map((l:any)=>({
          data:{ id:l.id, source:l.from.node, target:l.to.node, label:l.label || (l.vlan?`VLAN ${l.vlan}`:l.type) }
        }))
      ],
      style:[
        { selector:'node', style:{
          'label':'data(label)','text-valign':'bottom','text-halign':'center','width':'60px','height':'60px',
          'background-color':'#95a5a6','border-width':2,'border-color':'#2c3e50'
        }},
        { selector:'edge', style:{ 'width':2, 'line-color':'#aaa', 'curve-style':'bezier', 'label':'data(label)' } }
      ],
      layout:{ name:'dagre' }
    });
    cy.on('tap','node', evt => setSelected(evt.target.data('device_id')));
    cyRef.current = cy;
  },[diagram]);

  useEffect(()=>{
    if (!cyRef.current) return;
    deviceStatuses.forEach(s => {
      const node = cyRef.current!.nodes().filter(`[device_id = "${s.id}"]`);
      if (node.nonempty()) {
        let color = '#2ecc71';
        if (s.status==='warning') color = '#f1c40f';
        if (s.status==='critical') color = '#e74c3c';
        node.style('border-color', color);
      }
    });
  },[deviceStatuses]);

  return (
    <>
      <div ref={containerRef} style={{width:'100%', height:500, border:'1px solid #ccc', borderRadius:6}}/>
      <DeviceDetailsSidebar deviceId={selected} onClose={()=>setSelected(null)} />
    </>
  );
};
export default DiagramCanvasLive;
