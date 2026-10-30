/**
 * Starts the storefront for the end-to-end tests: a production build served by
 * `next start` on :3100.
 *
 * A production server is used on purpose. It is what customers get, so the
 * page cache, the real 404 status and the cache refresh from the backend behave
 * as they do live, and it does not fight with a `next dev` server you may
 * already have running for this project (Next allows only one dev server per
 * folder).
 *
 * Builds first unless E2E_SKIP_BUILD=1, which reuses the previous build (for
 * quick reruns when only the tests changed).
 */
import { spawn, spawnSync } from "node:child_process";
import { createRequire } from "node:module";

const require = createRequire(import.meta.url);
const nextBin = require.resolve("next/dist/bin/next");
const PORT = process.env.E2E_FRONTEND_PORT || "3100";

if (process.env.E2E_SKIP_BUILD !== "1") {
  const build = spawnSync(process.execPath, [nextBin, "build"], { stdio: "inherit", env: process.env });
  if (build.status !== 0) process.exit(build.status ?? 1);
}

const server = spawn(process.execPath, [nextBin, "start", "-p", PORT], { stdio: "inherit", env: process.env });
const stop = () => server.kill();
process.on("SIGTERM", stop);
process.on("SIGINT", stop);
server.on("exit", (code) => process.exit(code ?? 0));
