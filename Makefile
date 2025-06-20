# NewsBear Makefile
# Simple commands for common tasks

.PHONY: install start dev production test clean help

# Default target
help:
	@echo "NewsBear - Available commands:"
	@echo ""
	@echo "  make install     - Run the auto-installer"
	@echo "  make start       - Start the server (port 5000)"
	@echo "  make dev         - Start development server (port 3000, localhost)"
	@echo "  make production  - Start production server (port 80)"
	@echo "  make test        - Run basic syntax checks"
	@echo "  make clean       - Clean temporary files and logs"
	@echo "  make help        - Show this help message"
	@echo ""

# Install dependencies and set up
install:
	@echo "🔧 Running NewsBear installer..."
	php install.php

# Start server on default port
start:
	@echo "🚀 Starting NewsBear server..."
	php start.php

# Development server
dev:
	@echo "🛠️  Starting development server..."
	php start.php 3000 localhost

# Production server
production:
	@echo "🏭 Starting production server..."
	php start.php 80

# Run tests
test:
	@echo "🧪 Running syntax checks..."
	@php -l index.php
	@php -l start.php
	@php -l install.php
	@echo "✅ All syntax checks passed"

# Clean temporary files
clean:
	@echo "🧹 Cleaning temporary files..."
	@find . -name "*.tmp" -delete 2>/dev/null || true
	@find . -name "*.log" -delete 2>/dev/null || true
	@find data/cache -name "*" -not -name ".gitkeep" -delete 2>/dev/null || true
	@echo "✅ Cleanup complete"

# Quick setup (install + start)
setup: install start