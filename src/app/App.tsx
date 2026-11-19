import { Suspense } from "react";
import { RouterProvider } from "react-router";
import { AppProvider } from "../store";
import { router } from "./routes";

export default function App() {
  return (
    <AppProvider>
      <Suspense
        fallback={
          <div className="route-loader">
            <small>opening MINERVA…</small>
          </div>
        }
      >
        <RouterProvider router={router} />
      </Suspense>
    </AppProvider>
  );
}
