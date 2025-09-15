#!/usr/bin/env node
// tools/mcp/my-mcp/index.js
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { resolve } from "node:path";
import { readFile } from "node:fs/promises";

const PROJECT_ROOT = resolve(process.env.PROJECT_ROOT || process.cwd());
const safe = (p) => resolve(PROJECT_ROOT, p);

const server = new McpServer({ name: "my-mcp", version: "0.1.0" });

// Tool 1: echo (do testów)
server.registerTool(
  "echo",
  {
    title: "Echo text",
    description: "Zwraca to co podasz.",
    inputSchema: {
      type: "object",
      properties: {
        text: { type: "string" }
      },
      required: ["text"]
    },
  },
  async ({ text }) => ({ content: [{ type: "text", text }] })
);

// Tool 2: read_file (read-only z katalogu projektu)
server.registerTool(
  "read_file",
  {
    title: "Read file relative to project root (RO)",
    description: "Read a file from the project directory",
    inputSchema: {
      type: "object",
      properties: {
        path: { type: "string", description: "np. README.md lub src/index.ts" }
      },
      required: ["path"]
    },
  },
  async ({ path }) => {
    const full = safe(path);
    const data = await readFile(full, "utf8");
    return { content: [{ type: "text", text: data }] };
  }
);

async function main() {
  try {
    const transport = new StdioServerTransport();
    await server.connect(transport);
  } catch (error) {
    console.error("Error starting MCP server:", error);
    process.exit(1);
  }
}

main().catch(console.error);
