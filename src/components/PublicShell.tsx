import { useEffect, useState } from "react";
import { Link, NavLink, Outlet } from "react-router";
import { Menu, X } from "lucide-react";
import { Brand } from "./Brand";
import { useApp } from "../store";

export function PublicShell() {
  const [scrolled, setScrolled] = useState(false);
  const [menu, setMenu] = useState(false);
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 40);
    onScroll();
    window.addEventListener("scroll", onScroll);
    return () => window.removeEventListener("scroll", onScroll);
  }, []);
  const nav = [
    ["/", "home."],
    ["/articles", "articles."],
    ["/about", "about."],
    ["/leaderboard", "leaderboard."],
  ];
  return (
    <div>
      <header className={`public-nav ${scrolled ? "scrolled" : ""}`}>
        <Brand />
        <nav className="desktop-nav" aria-label="Main navigation">
          {nav.map(([to, label]) => (
            <NavLink key={to} to={to} end={to === "/"}>
              {label}
            </NavLink>
          ))}
        </nav>
        <div className="nav-actions">
          <Link className="btn btn-small" to="/login">
            sign in
          </Link>
          <button className="menu-button" aria-label="Open menu" onClick={() => setMenu(true)}>
            <Menu />
          </button>
        </div>
      </header>
      {menu && (
        <div className="mobile-menu">
          <button aria-label="Close menu" onClick={() => setMenu(false)}>
            <X />
          </button>
          <Brand />
          {nav.map(([to, label], i) => (
            <Link
              key={to}
              to={to}
              onClick={() => setMenu(false)}
              style={{ animationDelay: `${i * 60}ms` }}
            >
              {label}
            </Link>
          ))}
          <Link className="mobile-signin" to="/login" onClick={() => setMenu(false)}>
            Sign in
          </Link>
        </div>
      )}
      <main>
        <Outlet />
      </main>
      <footer className="footer">
        <div className="meander" />
        <div className="footer-grid">
          <div>
            <Brand light />
            <p>Where scholarly curiosity meets public conversation.</p>
          </div>
          <div>
            <span className="eyebrow">explore.</span>
            <Link to="/articles">The library</Link>
            <Link to="/leaderboard">Live leaderboard</Link>
            <Link to="/about">Our story</Link>
          </div>
          <div>
            <span className="eyebrow">contact.</span>
            <a href="mailto:embs@sjp.ac.lk">embs@sjp.ac.lk</a>
            <p>
              University of Sri Jayewardenepura
              <br />
              Nugegoda, Sri Lanka
            </p>
          </div>
        </div>
        <div className="footer-bottom">
          <span>© 2025 MINERVA</span>
          <span>IEEE EMBS Student Branch Chapter · USJ</span>
        </div>
      </footer>
      <AuthGate />
    </div>
  );
}

function AuthGate() {
  const { authOpen, setAuthOpen } = useApp();
  if (!authOpen) return null;
  return (
    <div className="modal-backdrop" role="presentation" onMouseDown={() => setAuthOpen(false)}>
      <div
        className="auth-gate"
        role="dialog"
        aria-modal="true"
        aria-labelledby="gate-title"
        onMouseDown={(e) => e.stopPropagation()}
      >
        <button className="modal-close" aria-label="Close" onClick={() => setAuthOpen(false)}>
          <X />
        </button>
        <img className="gate-emblem" src="/minerva-logo.png" alt="" />
        <span className="eyebrow">a small threshold.</span>
        <h2 id="gate-title">
          wisdom requires
          <br />
          <em>identity.</em>
        </h2>
        <p>Sign in to cast your vote. Reading, rankings and every article remain open to all.</p>
        <div className="gate-actions">
          <Link className="btn light" to="/login" onClick={() => setAuthOpen(false)}>
            sign in to vote
          </Link>
          <Link className="btn outline-light" to="/register" onClick={() => setAuthOpen(false)}>
            create account
          </Link>
        </div>
        <button className="continue" onClick={() => setAuthOpen(false)}>
          continue browsing
        </button>
      </div>
    </div>
  );
}
