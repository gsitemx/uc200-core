import React from "react";
import Sidebar from "./Sidebar";
import NOCView from "../modules/dashboard/NOCView";
const App: React.FC = () => {
  return (
    <div style={{ display: "flex", minHeight: "100vh" }}>
      <Sidebar />
      <div style={{ flex: 1, padding: 20 }}>
        <h1>Network Monitoring System</h1>
        <NOCView />
      </div>
    </div>
  );
};
export default App;
