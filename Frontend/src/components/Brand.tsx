import { Link } from "react-router";

export function OwlMark({ light = false }: { light?: boolean }) {
  return (
    <svg
      viewBox="0 0 64 64"
      className={`owl-mark ${light ? "text-parchment" : "text-ink"}`}
      aria-hidden="true"
    >
      <path
        d="M12 13c8 0 14 3 20 10 6-7 12-10 20-10-1 11-3 18-8 23 0 12-5 19-12 24-7-5-12-12-12-24-5-5-7-12-8-23Z"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
      />
      <circle cx="23" cy="30" r="6" fill="none" stroke="currentColor" strokeWidth="2" />
      <circle cx="41" cy="30" r="6" fill="none" stroke="currentColor" strokeWidth="2" />
      <circle cx="23" cy="30" r="2" fill="currentColor" />
      <circle cx="41" cy="30" r="2" fill="currentColor" />
      <path d="m29 39 3 4 3-4M24 50h16" fill="none" stroke="currentColor" strokeWidth="2" />
    </svg>
  );
}

export function Brand({ light = false }: { light?: boolean }) {
  return (
    <Link to="/" className={`brand ${light ? "text-parchment" : ""}`}>
      <img className="brand-logo" src="/minerva-logo.png" alt="MINERVA" />
    </Link>
  );
}
