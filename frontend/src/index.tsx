import React from "react";
import { createRoot } from "react-dom/client";
import App from "./core/App";

const root = createRoot(document.getElementById("root")!);
root.render(<App />);
