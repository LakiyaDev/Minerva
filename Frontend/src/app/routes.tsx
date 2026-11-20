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
const AuthCallback = lazy(() =>
  import("../pages/Auth").then((m) => ({ default: m.AuthCallback })),
);
const Register = lazy(() => import("../pages/Auth").then((m) => ({ default: m.Register })));
const ForgotPassword = lazy(() =>
  import("../pages/Auth").then((m) => ({ default: m.ForgotPassword })),
);
const ResetPassword = lazy(() =>
  import("../pages/Auth").then((m) => ({ default: m.ResetPassword })),
);
const Dashboard = lazy(() =>
  import("../pages/Participant").then((m) => ({ default: m.Dashboard })),
);
const MyVotes = lazy(() => import("../pages/Participant").then((m) => ({ default: m.MyVotes })));
const MyComments = lazy(() =>
  import("../pages/Participant").then((m) => ({ default: m.MyComments })),
);
const Notifications = lazy(() =>
  import("../pages/Participant").then((m) => ({ default: m.Notifications })),
);
const Profile = lazy(() => import("../pages/Participant").then((m) => ({ default: m.Profile })));
const PublisherDashboard = lazy(() =>
  import("../pages/Publisher").then((m) => ({ default: m.PublisherDashboard })),
);
const PublisherArticleEditor = lazy(() =>
  import("../pages/Publisher").then((m) => ({ default: m.PublisherArticleEditor })),
);
const AdminOverview = lazy(() =>
  import("../pages/Admin").then((m) => ({ default: m.AdminOverview })),
);
const AdminArticles = lazy(() =>
  import("../pages/Admin").then((m) => ({ default: m.AdminArticles })),
);
const AdminComments = lazy(() =>
  import("../pages/Admin").then((m) => ({ default: m.AdminComments })),
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
const AdminAuditLogs = lazy(() =>
  import("../pages/Admin").then((m) => ({ default: m.AdminAuditLogs })),
);

function RoleShell({ requiredRole }: { requiredRole: "participant" | "publisher" | "admin" }) {
  const { role, authChecked, isAdmin, isPublisher, hasPermission } = useApp();
  const location = useLocation();

  if (!authChecked) {
    return (
      <div className="empty-state">
        <span>◉</span>
        <h2>verifying session.</h2>
        <p>Checking your credentials with MINERVA.</p>
      </div>
    );
  }

  const allowed =
    requiredRole === "admin"
      ? role === "admin" || isAdmin
      : requiredRole === "publisher"
        ? role === "publisher" || role === "admin" || isPublisher || hasPermission("article.create")
        : role !== "guest";

  if (!allowed) {
    return <Navigate replace to="/login" state={{ from: location.pathname, requiredRole }} />;
  }

  return <AppShell shell={requiredRole} admin={requiredRole === "admin"} />;
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
  { path: "/auth/callback", Component: AuthCallback },
  { path: "/register", Component: Register },
  { path: "/forgot-password", Component: ForgotPassword },
  { path: "/staff/reset-password", Component: ResetPassword },
  { path: "/reset-password", Component: ResetPassword },
  {
    Component: () => <RoleShell requiredRole="participant" />,
    children: [
      { path: "/dashboard", Component: Dashboard },
      { path: "/my-votes", Component: MyVotes },
      { path: "/my-comments", Component: MyComments },
      { path: "/notifications", Component: Notifications },
      { path: "/profile", Component: Profile },
    ],
  },
  {
    Component: () => <RoleShell requiredRole="publisher" />,
    children: [
      { path: "/publisher", Component: PublisherDashboard },
      { path: "/publisher/create", Component: PublisherArticleEditor },
      { path: "/publisher/edit/:id", Component: PublisherArticleEditor },
    ],
  },
  {
    Component: () => <RoleShell requiredRole="admin" />,
    children: [
      { path: "/admin", Component: AdminOverview },
      { path: "/admin/articles", Component: AdminArticles },
      { path: "/admin/comments", Component: AdminComments },
      { path: "/admin/create", Component: CreateArticle },
      { path: "/admin/voting", Component: VotingManagement },
      { path: "/admin/results", Component: Results },
      { path: "/admin/participants", Component: Participants },
      { path: "/admin/audit-logs", Component: AdminAuditLogs },
      { path: "/admin/settings", Component: AdminSettings },
    ],
  },
  { path: "*", Component: NotFound },
]);
