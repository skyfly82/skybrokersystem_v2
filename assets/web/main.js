import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.js';

const el = document.getElementById('root');
if (el) {
  const root = createRoot(el);
  root.render(React.createElement(App));
}

