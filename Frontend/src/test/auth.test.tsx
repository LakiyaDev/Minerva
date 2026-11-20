import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { createMemoryRouter, RouterProvider } from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ForgotPassword, ResetPassword } from "../pages/Auth";

const mockForgotStaffPassword = vi.fn();
const mockResetStaffPassword = vi.fn();

vi.mock("../services/authService", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../services/authService")>();
  return {
    ...actual,
    forgotStaffPassword: (...args: unknown[]) => mockForgotStaffPassword(...args),
    resetStaffPassword: (...args: unknown[]) => mockResetStaffPassword(...args),
  };
});

const mockUseApp = vi.fn();
vi.mock("../store", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../store")>();
  return {
    ...actual,
    useApp: () => mockUseApp(),
  };
});

describe("Auth Guards & Password Recovery (FE-23, FE-71)", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseApp.mockReturnValue({
      role: "guest",
      authChecked: true,
      isAdmin: false,
      isPublisher: false,
      hasPermission: () => false,
    });
  });

  it("submits staff forgot password form and displays inbox confirmation", async () => {
    mockForgotStaffPassword.mockResolvedValueOnce(undefined);
    const user = userEvent.setup();

    const router = createMemoryRouter(
      [
        { path: "/forgot-password", Component: ForgotPassword },
        { path: "/login", element: <div>Login Page</div> },
      ],
      { initialEntries: ["/forgot-password"] },
    );

    render(<RouterProvider router={router} />);

    await user.type(screen.getByPlaceholderText("name@university.edu"), "staff@minerva.edu");
    await user.click(screen.getByRole("button", { name: /send reset link/i }));

    await waitFor(() => {
      expect(mockForgotStaffPassword).toHaveBeenCalledWith("staff@minerva.edu");
    });

    expect(screen.getByText(/Check your inbox/i)).toBeInTheDocument();
  });

  it("pre-fills token & email from URL on ResetPassword and submits reset", async () => {
    mockResetStaffPassword.mockResolvedValueOnce(undefined);
    const user = userEvent.setup();

    const router = createMemoryRouter(
      [
        { path: "/staff/reset-password", Component: ResetPassword },
        { path: "/login", element: <div>Login Page</div> },
      ],
      { initialEntries: ["/staff/reset-password?token=abc123tok&email=editor%40minerva.edu"] },
    );

    render(<RouterProvider router={router} />);

    const emailInput = screen.getByPlaceholderText("name@university.edu") as HTMLInputElement;
    expect(emailInput.value).toBe("editor@minerva.edu");
    expect(emailInput.readOnly).toBe(true);

    await user.type(screen.getByPlaceholderText("Minimum 12 characters"), "StrongPass!2026");
    await user.type(screen.getByPlaceholderText("Repeat new password"), "StrongPass!2026");
    await user.click(screen.getByRole("button", { name: /reset password/i }));

    await waitFor(() => {
      expect(mockResetStaffPassword).toHaveBeenCalledWith({
        token: "abc123tok",
        email: "editor@minerva.edu",
        password: "StrongPass!2026",
        password_confirmation: "StrongPass!2026",
      });
    });

    expect(screen.getByText(/Password updated\./i)).toBeInTheDocument();
  });
});
