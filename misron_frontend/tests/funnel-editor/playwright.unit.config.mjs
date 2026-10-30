import { defineConfig } from "@playwright/test";

/** Unit tests of the funnel's shared logic. No browser, no servers. */
export default defineConfig({
  testDir: "./unit",
  reporter: [["list"]],
  outputDir: "../../test-results/funnel-core",
});
