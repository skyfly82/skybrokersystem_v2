#!/usr/bin/env node

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";

const server = new McpServer({
  name: "test-mcp",
  version: "1.0.0"
});

// Simple echo tool
server.registerTool(
  "echo",
  {
    title: "Echo",
    description: "Echo back the input text",
    inputSchema: {
      type: "object",
      properties: {
        text: {
          type: "string",
          description: "Text to echo back"
        }
      },
      required: ["text"]
    }
  },
  async ({ text }) => {
    return {
      content: [
        {
          type: "text",
          text: `Echo: ${text}`
        }
      ]
    };
  }
);

async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch(console.error);