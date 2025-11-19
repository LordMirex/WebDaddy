# WebDaddy Empire

## Overview
WebDaddy Empire is a PHP-based marketplace platform designed for selling website templates and digital tools. It aims to provide a robust and user-friendly experience for both sellers and buyers, built with a SQLite database, Tailwind CSS for styling, and custom PHP components. The project focuses on efficient media handling, a seamless user experience for content consumption, and a secure, portable architecture.

## User Preferences
I want the agent to maintain a clean and optimized codebase, focusing on performance and user experience. Ensure that any changes made are well-documented and follow best practices. Prioritize solutions that are robust, maintainable, and avoid introducing unnecessary complexity or dependencies. I prefer detailed explanations for significant changes and want the agent to ask before making major architectural decisions or large-scale modifications.

## System Architecture

### UI/UX Decisions
The platform utilizes Tailwind CSS for a utility-first approach to styling, ensuring a consistent and modern design. Key UI/UX features include a smart video loading system with preloading capabilities for near-instant playback, and animated loading instructions to enhance user feedback during content retrieval. The video player is adaptive, displaying videos in their natural aspect ratio, and includes a functional mute/unmute toggle.

### Technical Implementations
- **Database:** Uses SQLite (`database/webdaddy.db`) for data storage.
- **Backend:** PHP 8.2+ with custom routing handled by `router.php`.
- **Frontend:** Primarily vanilla JavaScript for dynamic interactions and performance optimizations.
- **Media Handling:** Employs a local file storage system within the `uploads/` directory. Videos are uploaded directly without server-side processing, favoring client-side optimization techniques like hover-based and Intersection Observer preloading.
- **URL Management:** Stores relative paths for all uploaded media to ensure environment portability across development, staging, and production.
- **Error Handling:** Production configuration disables display of errors to users, logging them instead for security and a professional user experience.
- **Admin Tools:** Includes a safe database reset tool that clears all test data while preserving critical admin accounts and system settings, crucial for production launches.

### Feature Specifications
- **Template and Tool Marketplace:** Core functionality for listing and selling digital products.
- **Smart Video Loading:** Implements a sophisticated preloading system that buffers videos based on user interaction (hover) and visibility (Intersection Observer), adapting to network conditions. It includes SessionStorage caching for instant reopening of recently viewed videos.
- **Animated Loading Instructions:** Provides user-friendly, animated instructions during content loading (videos and iframes) to improve perceived performance.
- **Adaptive Video Player:** Dynamically adjusts video display to maintain aspect ratio, enhancing the viewing experience for various video formats.
- **Database Reset Tool:** A secure administrative tool (`admin/reset-database.php`) to clear non-essential data before production launch, safeguarding critical configuration and user accounts.

### System Design Choices
- The system prioritizes a lightweight architecture, avoiding heavy server-side dependencies like FFmpeg for video processing.
- Network-aware logic in the video preloader intelligently adapts to user connection speeds, optimizing resource usage.
- Strict memory management is implemented for video buffering and caching to prevent bloat and ensure application stability.
- A custom upload handler stores relative URLs for portability, preventing broken links when migrating environments.
- The system is designed for a fast and responsive user experience, particularly concerning multimedia content.

## External Dependencies
- **SQLite:** Used as the primary database.
- **Tailwind CSS:** Frontend styling framework.
- **Vanilla JavaScript:** For client-side interactivity and performance optimizations.
- **PHP 8.2+:** Backend language.