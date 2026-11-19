import {
  Bell,
  BookOpen,
  ChartNoAxesColumn,
  FilePlus2,
  History,
  House,
  LogOut,
  Menu,
  Settings,
  UserRound,
  UsersRound,
  X,
} from "lucide-react";
import { NavLink, Outlet, useNavigate } from "react-router";
import { useEffect, useState } from "react";
import { Brand } from "./Brand";
import { useApp } from "../store";

export function AppShell({ admin = false }: { admin?: boolean }) {
  const [open, setOpen] = useState(false);
  const { unread, setRole } = useApp();
  const navigate = useNavigate();
  useEffect(() => {
    document.body.classList.remove("book-ruins-theme");
  }, []);
  const links = admin
    ? [
        ["/admin", "Overview", House],
        ["/admin/articles", "Articles", BookOpen],
        ["/admin/create", "Create article", FilePlus2],
        ["/admin/voting", "Voting", History],
        ["/admin/results", "Results", ChartNoAxesColumn],
        ["/admin/participants", "Participants", UsersRound],
        ["/admin/settings", "Settings", Settings],
      ]
    : [
        ["/dashboard", "Dashboard", House],
        ["/articles", "Articles", BookOpen],
        ["/my-votes", "My votes", History],
        ["/notifications", "Notifications", Bell],
        ["/profile", "Profile", UserRound],
      ];
  const logout = () => {
    setRole("guest");
    navigate("/");
  };
  return (
    <div className={`app-shell ${admin ? "admin-shell" : ""}`}>
      <aside className={open ? "open" : ""}>
        <button className="close-drawer" onClick={() => setOpen(false)}>
          <X />
        </button>
        <Brand light />
        <span className="shell-label">{admin ? "editor portal" : "participant desk"}</span>
        <nav>
          {links.map(([to, label, Icon]) => (
            <NavLink
              key={to as string}
              to={to as string}
              end={to === "/admin"}
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
          <button onClick={logout}>
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
            <span className="eyebrow">{admin ? "editor workspace" : "monday, 08 december"}</span>
            <strong>{admin ? "MINERVA administration" : "Welcome back"}</strong>
          </div>
          <div className="profile-chip">
            <button aria-label="Notifications">
              <Bell size={18} />
              {unread > 0 && <i />}
            </button>
            <span>AP</span>
            <div>
              <strong>{admin ? "Editor" : "Participant"}</strong>
              <small>{admin ? "Administration" : "Account"}</small>
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
