/**
 * Dashboard pagination.
 *
 * Re-exports the shared Pagination so the dashboard and the shop page use the
 * same control. The previous implementation rendered one button per page with
 * no windowing, so a 200-page order history produced 200 buttons.
 *
 * The import path is kept so existing call sites do not change.
 */

export { default } from "../ui/Pagination";
