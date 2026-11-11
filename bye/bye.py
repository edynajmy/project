import tkinter as tk
import pygame
import time

pygame.mixer.init()
pygame.mixer.music.load("bye.mp3")

LYRICS = [
    (0, "It's over, it's over, oh yeah"),
    (2, "This ain't the first time"),
    (3.5, "I've been hostage to these tears"),
    (6, "I can't believe I'm finally"),
    (8, "moving through my fears"),
    (11, "At least I know how hard"),
    (12.5, "we tried, both you and me"),
    (15, "Didn't we? Didn't we?")
]

BOX_W, BOX_H = 350, 300
FONT = ("Helvetica", 25, "bold")
BG_COLOR = "#fdfdf5"
FG_COLOR = "#111111"

RISE_SPEED = 150
BOTTOM_SPAWN_OFFSET = 0
GAP = 40

class LyricCard:
    def __init__(self, parent, text, x, y):
        self.win = tk.Toplevel(parent)
        self.win.overrideredirect(True) 
        self.win.attributes("-topmost", True)
        self.win.configure(bg=BG_COLOR)
        self.win.geometry(f"{BOX_W}x{BOX_H}+{int(x)}+{int(y)}")
        self.win.resizable(False, False)
        self.full_text = text
        self.label = tk.Label(
            self.win,
            text="",
            font=FONT,
            bg=BG_COLOR,
            fg=FG_COLOR,
            wraplength=BOX_W - 25,
            justify="center"
        )
        self.label.pack(expand=True, fill="both", padx=15, pady=15)
        self.typewriter_index = 0
        self.x = x
        self.y = float(y)
        self.flip_state = False
        self.typewriter()

    def typewriter(self):
        if self.typewriter_index <= len(self.full_text):
            self.label.config(text=self.full_text[:self.typewriter_index])
            self.typewriter_index += 1
            self.win.after(50, self.typewriter)

    def rise(self, dy):
        self.y -= dy
        self.win.geometry(f"{BOX_W}x{BOX_H}+{int(self.x)}+{int(self.y)}")

    def is_offscreen(self):
        return self.y + BOX_H < -50

class LyricFloatApp:
    def __init__(self, root):
        self.root = root
        self.screen_w = root.winfo_screenwidth()
        self.screen_h = root.winfo_screenheight()
        self.next_lyric_idx = 0
        self.boxes = []
        self.last_frame_time = None
        self.current_side = "left"
        self.start()

    def random_safe_x(self):
        center_x = self.screen_w // 2
        spacing = BOX_W + 60
        left_x = center_x - spacing
        right_x = center_x + 60
        if self.current_side == "left":
            self.current_side = "right"
            return left_x
        else:
            self.current_side = "left"
            return right_x

    def start(self):
        self.root.iconify()  
        self.start_time = time.time()
        self.last_frame_time = self.start_time
        self.tick()
        self.flip_all()
        pygame.mixer.music.play()

    def flip_all(self):
        for box in self.boxes:
            if box.flip_state:
                box.win.config(bg="white")
                box.label.config(bg="white", fg="black")
            else:
                box.win.config(bg="black")
                box.label.config(bg="black", fg="white")
            box.flip_state = not box.flip_state
        self.root.after(150, self.flip_all)

    def tick(self):
        now = time.time()
        elapsed = now - self.start_time
        dt = now - self.last_frame_time
        self.last_frame_time = now
        while (self.next_lyric_idx < len(LYRICS) and
               LYRICS[self.next_lyric_idx][0] <= elapsed):
            t, text = LYRICS[self.next_lyric_idx]
            x = self.random_safe_x()
            if self.boxes:
                y = self.boxes[-1].y + BOX_H + GAP
            else:
                y = self.screen_h - BOX_H - BOTTOM_SPAWN_OFFSET
            box = LyricCard(self.root, text, x, y)
            self.boxes.append(box)
            self.next_lyric_idx += 1
        dy = RISE_SPEED * dt
        for box in self.boxes:
            box.rise(dy)
        still_visible = []
        for box in self.boxes:
            if box.is_offscreen():
                try:
                    box.win.destroy()
                except tk.TclError:
                    pass
            else:
                still_visible.append(box)
        self.boxes = still_visible
        if self.next_lyric_idx < len(LYRICS) or self.boxes:
            self.root.after(16, self.tick)

if __name__ == "__main__":
    root = tk.Tk()
    app = LyricFloatApp(root)
    root.mainloop()