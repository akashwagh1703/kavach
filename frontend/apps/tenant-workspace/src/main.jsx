import React from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import { createKavachStore, KavachTheme } from '@kavach/app-kit';
import App from './App.jsx';
import '@kavach/app-kit/styles.css';
import './styles.css';

const store = createKavachStore();
const queryClient = new QueryClient({ defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: false } } });

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <Provider store={store}>
      <QueryClientProvider client={queryClient}>
        <KavachTheme>
          <BrowserRouter>
            <App />
          </BrowserRouter>
        </KavachTheme>
      </QueryClientProvider>
    </Provider>
  </React.StrictMode>,
);
