import { useEffect, useState } from "react";

export type NOCDevice = {
  id: number;
  name: string;
  type: string;
  status: "ok" | "warning" | "critical";
  severity?: string | null;
  last_checked: string;
};

export const useNOCWS = () => {
  const [devices, setDevices] = useState<NOCDevice[]>([]);

  useEffect(() => {
    const ws = new WebSocket("ws://localhost:8000/ws/noc");
    ws.onmessage = (e) => setDevices(JSON.parse(e.data));
    return () => ws.close();
  }, []);

  return devices;
};
