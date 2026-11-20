import {
  Bell,
  BookOpen,
  ChartNoAxesColumn,
  Feather,
  FilePlus2,
  History,
  House,
  LogOut,
  Menu,
  MessageSquare,
  ScrollText,
  Settings,
  UserRound,
  UsersRound,
  X,
} from "lucide-react";
import { NavLink, Outlet, useNavigate } from "react-router";
import { useEffect, useState } from "react";
import { Brand } from "./Brand";
import { useApp } from "../store";

export function AppShell({
  admin = false,
  shell,
}: {
  admin?: boolean;
  shell?: "participant" | "publisher" | "admin";
}) {
  const [open, setOpen] = useState(false);
  const { unread, logout, user, isPublisher, hasPermission } = useApp();
  const navigate = useNavigate();

  const activeShell = shell ?? (admin ? "admin" : "participant");

  useEffect(() => {
    document.body.classList.remove("book-ruins-theme");
  }, []);

  const canPublish = isPublisher || hasPermission("article.create");

  const links =
    activeShell === "admin"
      ? [
          ["/admin", "Overview", House],
          ["/admin/articles", "Articles", BookOpen],
          ["/admin/comments", "Comments", MessageSquare],
          ["/admin/create", "Create article", FilePlus2],
          ["/admin/voting", "Voting", History],
          ["/admin/results", "Results", ChartNoAxesColumn],
          ["/admin/participants", "Participants", UsersRound],
          ["/admin/audit-logs", "Audit logs", ScrollText],
          ["/admin/settings", "Settings", Settings],
        ]
      : activeShell === "publisher"
        ? [
            ["/publisher", "My articles", Feather],
            ["/publisher/create", "New article", FilePlus2],
            ["/articles", "Library", BookOpen],
            ["/dashboard", "Participant desk", House],
            ["/my-votes", "My votes", History],
            ["/my-comments", "My comments", MessageSquare],
            ["/profile", "Profile", UserRound],
          ]
        : [
            ["/dashboard", "Dashboard", House],
            ...(canPublish ? [["/publisher", "Publisher desk", Feather] as const] : []),
            ["/articles", "Articles", BookOpen],
            ["/my-votes", "My votes", History],
            ["/my-comments", "My comments", MessageSquare],
            ["/notifications", "Notifications", Bell],
            ["/profile", "Profile", UserRound],
          ];

  const handleLogout = async () => {
    await logout();
    navigate("/");
  };

  const displayName =
    user?.name ||
    (activeShell === "admin"
      ? "Editor"
      : activeShell === "publisher"
        ? "Publisher"
        : "Participant");

  const initials = displayName
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("");

  return (
    <div className={`app-shell ${activeShell === "admin" ? "admin-shell" : ""}`}>
      <aside className={open ? "open" : ""}>
        <button className="close-drawer" onClick={() => setOpen(false)}>
          <X />
        </button>
        <Brand light />
        <span className="shell-label">
          {activeShell === "admin"
            ? "editor portal"
            : activeShell === "publisher"
              ? "publisher studio"
              : "participant desk"}
        </span>
        <nav>
          {links.map(([to, label, Icon]) => (
            <NavLink
              key={to as string}
              to={to as string}
              end={to === "/admin" || to === "/publisher"}
              onClick={() => setOpen(false)}
            >
              <Icon size={18} />
              <span>{label as string}</span>
              {label === "Notifications" && unread > 0 && <b>{unread}</b>}
            </NavLink>
          ))}
        </nav>
        <div className="sidebar-foot">
          <NavLink to="/">
            <BookOpen size={18} /> Public site
          </NavLink>
          <button onClick={() => void handleLogout()}>
            <LogOut size={18} /> Sign out
          </button>
        </div>
      </aside>
      <div className="app-content">
        <header className="app-topbar">
          <button className="drawer-trigger" onClick={() => setOpen(true)}>
            <Menu />
          </button>
          <div>
            <span className="eyebrow">
              {activeShell === "admin"
                ? "editor workspace"
                : activeShell === "publisher"
                  ? "publisher workspace"
                  : "minerva desk"}
            </span>
            <strong>
              {activeShell === "admin"
                ? "MINERVA administration"
                : activeShell === "publisher"
                  ? "Author studio"
                  : "Welcome back"}
            </strong>
          </div>
          <div className="profile-chip">
            <button aria-label="Notifications">
              <Bell size={18} />
              {unread > 0 && <i />}
            </button>
            <span>{initials || "MN"}</span>
            <div>
              <strong>{displayName}</strong>
              <small>
                {activeShell === "admin"
                  ? "Administration"
                  : activeShell === "publisher"
                    ? "Publisher"
                    : "Account"}
              </small>
            </div>
          </div>
        </header>
        <main>
          <Outlet />
        </main>
      </div>
    </div>
  );
}
