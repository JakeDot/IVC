from playwright.sync_api import sync_playwright

def capture_screenshots():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)

        # Screenshot 1: Lobby View
        page = browser.new_page(viewport={"width": 1280, "height": 800})
        page.goto("http://127.0.0.1:8080/")
        page.wait_for_selector("#room-lobby")
        page.fill("#room-input", "fortress-secure-room")
        page.fill("#nickname-input", "Cyber Sentinel")
        page.screenshot(path="docs/images/lobby.png")

        # Screenshot 2: Active Room & Video Stage View
        page.click("#btn-join-room")
        page.wait_for_selector("#video-stage", state="visible")
        page.screenshot(path="docs/images/room.png")

        browser.close()

if __name__ == "__main__":
    capture_screenshots()
