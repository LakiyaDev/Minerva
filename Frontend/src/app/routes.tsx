import { lazy } from "react";
import { createBrowserRouter, Navigate, useLocation } from "react-router";
import { PublicShell } from "../components/PublicShell";
import { AppShell } from "../components/AppShell";
import { useApp } from "../store";

const Home = lazy(() => import("../pages/Home").then((m) => ({ default: m.Home })));
const Articles = lazy(() => import("../pages/PublicPages").then((m) => ({ default: m.Articles })));
const ArticleDetail = lazy(() =>
  import("../pages/PublicPages").then((m) => ({ default: m.ArticleDetail })),
);
const Leaderboard = lazy(() =>
  import("../pages/PublicPages").then((m) => ({ default: m.Leaderboard })),
);
const About = lazy(() => import("../pages/PublicPages").then((m) => ({ default: m.About })));
const NotFound = lazy(() => import("../pages/PublicPages").then((m) => ({ default: m.NotFound })));
const Login = lazy(() => import("../pages/Auth").then((m) => ({ default: m.Login })));
const Register = lazy(() => import("../pages/Auth").then((m) => ({ default: m.Register })));
const ForgotPassword = lazy(() =>
  import("../pages/Auth").then((m) => ({ default: m.ForgotPassword })),
);
const Dashboard = lazy(() =>
  import("../pages/Participant").then((m) => ({ default: m.Dashboard })),
);
const MyVotes = lazy(() => import("../pages/Participant").then((m) => ({ default: m.MyVotes })));
const Notifications = lazy(() =>
  import("../pages/Participant").then((m) => ({ default: m.Notifications })),
);
const Profile = lazy(() => import("../pages/Participant").then((m) => ({ default: m.Profile })));
const AdminOverview = lazy(() =>
  import("../pages/Admin").then((m) => ({ default: m.AdminOverview })),
);
const AdminArticles = lazy(() =>
  import("../pages/Admin").then((m) => ({ default: m.AdminArticles })),
);
const CreateArticle = lazy(() =>
  import("../pages/Admin").then((m) => ({ default: m.CreateArticle })),
);
const VotingManagement = lazy(() =>
  import("../pages/Admin").then((m) => ({ default: m.VotingManagement })),
);
const Results = lazy(() => import("../pages/Admin").then((m) => ({ default: m.Results })));
const Participants = lazy(() =>
  import("../pages/Admin").then((m) => ({ default: m.Participants })),
);
const AdminSettings = lazy(() =>
  import("../pages/Admin").then((m) => ({ default: m.AdminSettings })),
);

function RoleShell({ requiredRole }: { requiredRole: "participant" | "admin" }) {
  const { role } = useApp();
  const location = useLocation();

  if (role !== requiredRole) {
    return <Navigate replace to="/login" state={{ from: location.pathname, requiredRole }} />;
  }

  return <AppShell admin={requiredRole === "admin"} />;
}

export const router = createBrowserRouter([
  {
    path: "/",
    Component: PublicShell,
    children: [
      { index: true, Component: Home },
      { path: "articles", Component: Articles },
      { path: "articles/:id", Component: ArticleDetail },
      { path: "about", Component: About },
      { path: "leaderboard", Component: Leaderboard },
    ],
  },
  { path: "/login", Component: Login },
  { path: "/register", Component: Register },
  { path: "/forgot-password", Component: ForgotPassword },
  {
    Component: () => <RoleShell requiredRole="participant" />,
    children: [
      { path: "/dashboard", Component: Dashboard },
      { path: "/my-votes", Component: MyVotes },
      { path: "/notifications", Component: Notifications },
      { path: "/profile", Component: Profile },
    ],
  },
  {
    Component: () => <RoleShell requiredRole="admin" />,
    children: [
      { path: "/admin", Component: AdminOverview },
      { path: "/admin/articles", Component: AdminArticles },
      { path: "/admin/create", Component: CreateArticle },
      { path: "/admin/voting", Component: VotingManagement },
      { path: "/admin/results", Component: Results },
      { path: "/admin/participants", Component: Participants },
      { path: "/admin/settings", Component: AdminSettings },
    ],
  },
  { path: "*", Component: NotFound },
]);
