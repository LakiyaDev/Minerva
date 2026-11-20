import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { createMemoryRouter, RouterProvider } from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AdminArticles, Participants } from "../pages/Admin";

const mockLoadAdminArticles = vi.fn();
const mockPublishAny = vi.fn();
const mockUnpublish = vi.fn();
const mockToggleFeatured = vi.fn();
const mockUseApp = vi.fn();

const mockGetAdminUsers = vi.fn();
const mockGetStaffInvitations = vi.fn();
const mockGrantPublisherPermission = vi.fn();
const mockRevokePublisherPermission = vi.fn();
const mockUpdateAdminUser = vi.fn();
const mockInviteStaffMember = vi.fn();

vi.mock("../store", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../store")>();
  return {
    ...actual,
    useApp: () => mockUseApp(),
  };
});

vi.mock("../services/userService", () => ({
  getAdminUsers: (...args: unknown[]) => mockGetAdminUsers(...args),
  getStaffInvitations: (...args: unknown[]) => mockGetStaffInvitations(...args),
  grantPublisherPermission: (...args: unknown[]) => mockGrantPublisherPermission(...args),
  revokePublisherPermission: (...args: unknown[]) => mockRevokePublisherPermission(...args),
  updateAdminUser: (...args: unknown[]) => mockUpdateAdminUser(...args),
  inviteStaffMember: (...args: unknown[]) => mockInviteStaffMember(...args),
}));

describe("Admin Article Unpublishing & User Management (FE-51, FE-52, FE-71)", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseApp.mockReturnValue({
      user: { id: "admin-1", name: "Chief Admin", roles: ["administrator"] },
      hasPermission: (p: string) => ["user.manage", "publisher.manage", "article.unpublish"].includes(p),
      categories: [{ id: "cat-1", name: "Bioinformatics", slug: "bioinformatics" }],
      loadAdminArticles: mockLoadAdminArticles,
      publishAny: mockPublishAny,
      unpublish: mockUnpublish,
      toggleFeatured: mockToggleFeatured,
    });
  });

  it("allows admins to unpublish/remove published articles with no approve/reject queue", async () => {
    mockLoadAdminArticles.mockResolvedValue([
      {
        id: "art-pub",
        slug: "live-paper",
        title: "Live Paper",
        author: "Dr. Ada",
        category: "Bioinformatics",
        category_slug: "bioinformatics",
        date: "20 Nov",
        abstract: "Abstract",
        excerpt: "Abstract",
        votes: 8,
        status: "published",
        is_featured: false,
        voting: "open",
        image: "",
      },
    ]);
    mockUnpublish.mockResolvedValueOnce({ ok: true });
    const user = userEvent.setup();

    const router = createMemoryRouter(
      [
        { path: "/admin/articles", Component: AdminArticles },
        { path: "/admin/create", element: <div>Create</div> },
        { path: "/articles/:id", element: <div>View</div> },
      ],
      { initialEntries: ["/admin/articles"] },
    );

    render(<RouterProvider router={router} />);

    expect(await screen.findByText("Live Paper")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /approve/i })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /reject/i })).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /unpublish \/ remove/i }));

    await waitFor(() => {
      expect(mockUnpublish).toHaveBeenCalledWith("art-pub");
    });
    expect(await screen.findByText("Article unpublished.")).toBeInTheDocument();
  });

  it("loads live users, grants publisher access, and sends staff invitations in User Management", async () => {
    mockGetAdminUsers.mockResolvedValue({
      data: [
        {
          id: "u-1",
          name: "Jane Researcher",
          email: "jane@uni.edu",
          is_active: true,
          email_verified: true,
          roles: ["participant"],
          direct_permissions: [],
          publisher_permission: null,
          last_login_at: null,
          created_at: "2026-11-10T10:00:00Z",
        },
      ],
      meta: { total: 1, last_page: 1 },
    });
    mockGetStaffInvitations.mockResolvedValue({ data: [] });
    mockGrantPublisherPermission.mockResolvedValueOnce(undefined);
    mockInviteStaffMember.mockResolvedValueOnce({
      id: "inv-1",
      email: "newstaff@uni.edu",
      role: "publisher",
      expires_at: "2026-11-27T10:00:00Z",
      accepted_at: null,
      invited_by: "Chief Admin",
      created_at: "2026-11-20T10:00:00Z",
    });

    const user = userEvent.setup();
    const router = createMemoryRouter([{ path: "/admin/participants", Component: Participants }], {
      initialEntries: ["/admin/participants"],
    });

    render(<RouterProvider router={router} />);

    expect(await screen.findByText("Jane Researcher")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /grant publisher/i }));
    await waitFor(() => {
      expect(mockGrantPublisherPermission).toHaveBeenCalledWith("u-1");
    });

    await user.click(screen.getByRole("button", { name: /invite staff/i }));
    await user.type(screen.getByPlaceholderText("staff@example.com"), "newstaff@uni.edu");
    await user.click(screen.getByRole("button", { name: /send invitation/i }));

    await waitFor(() => {
      expect(mockInviteStaffMember).toHaveBeenCalledWith("newstaff@uni.edu", "publisher");
    });
  });
});
