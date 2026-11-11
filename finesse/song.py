import math
import os
import random
import sys
import pygame

os.environ["SDL_VIDEO_WINDOW_POS"] = "0,0"

pygame.init()
pygame.font.init()


SPEED_MULTIPLIER = 1.0
BASE_FLOAT_SPEED = 2.1

BLACK_CARD_STYLE = ((0, 0, 0), (255, 255, 255))
WHITE_CARD_STYLE = ((255, 255, 255), (0, 0, 0))

LYRICS = [
    "When I'm walkin' with you",
    "I watch the whole room change",
    "Baby, that's what you do",
    "No, my baby, don't play",
    "Blame it on my confidence",
    "Oh, blame it on your measurements",
    "Shut that shit down on sight",
    "That's right",
]

LINE_DELAYS_SECONDS = [
    1.5,
    2.0,
    2.0,
    3.0,
    3.0,
    2.2,
    1.5,
    2.5,  
]

TYPE_SPEEDS_MS = [60, 60, 70, 70, 90, 60, 60, 150]
DEFAULT_TYPE_SPEED_MS = 80

CARD_WIDTH = 310
CARD_HEIGHT = 350
CARD_ROUNDING = 16
CARD_BORDER_WIDTH = 4

FONT_FAMILY = "Helvetica"
BODY_FONT_SIZE = 46 

TRANSPARENT_KEY = (255, 0, 128)



class FloatingCard:

    def __init__(self, text, current_step, total_steps, screen_w, screen_h, active_cards):
        self.text = text
        self.current_step = current_step
        self.total_steps = total_steps
        self.screen_w = screen_w
        self.screen_h = screen_h

        if self.current_step % 2 != 0:
            self.bg_color, self.text_color = BLACK_CARD_STYLE
        else:
            self.bg_color, self.text_color = WHITE_CARD_STYLE

        speed_index = self.current_step - 1
        if speed_index < len(TYPE_SPEEDS_MS):
            base_type_speed = TYPE_SPEEDS_MS[speed_index]
        else:
            base_type_speed = DEFAULT_TYPE_SPEED_MS

        self.type_speed_ms = max(1, int(base_type_speed / SPEED_MULTIPLIER))

        self.y = float(self.screen_h)
        self.x = float(self.calculate_random_non_overlapping_x(active_cards))

        self.type_index = 0
        self.last_type_time = 0

        self.spawn_time = pygame.time.get_ticks()
        self.next_triggered = False
        self.has_spawned_next = False

        self.font_body = pygame.font.SysFont(
            FONT_FAMILY, BODY_FONT_SIZE, bold=True
        )

    def calculate_random_non_overlapping_x(self, active_cards):
        margin = 30
        min_x = margin
        max_x = max(min_x, self.screen_w - CARD_WIDTH - margin)

        if not active_cards or max_x <= min_x:
            return random.randint(min_x, max_x)

        padding_x = 25
        padding_y = 30

        valid_candidates = []
        best_candidate = None
        max_min_distance = -1

        for _ in range(100):
            candidate_x = random.randint(min_x, max_x)
            candidate_rect = pygame.Rect(
                candidate_x - padding_x,
                int(self.y) - padding_y,
                CARD_WIDTH + (padding_x * 2),
                CARD_HEIGHT + (padding_y * 2)
            )

            has_collision = False
            current_min_dist = float('inf')

            for card in active_cards:
                other_rect = pygame.Rect(
                    int(card.x), int(card.y), CARD_WIDTH, CARD_HEIGHT
                )
                if candidate_rect.colliderect(other_rect):
                    has_collision = True

                dist = abs(candidate_x - card.x)
                if dist < current_min_dist:
                    current_min_dist = dist

            if not has_collision:
                valid_candidates.append(candidate_x)

            if current_min_dist > max_min_distance:
                max_min_distance = current_min_dist
                best_candidate = candidate_x

        if valid_candidates:
            return random.choice(valid_candidates)

        return best_candidate if best_candidate is not None else random.randint(min_x, max_x)

    def update(self, current_time):
        self.y -= (BASE_FLOAT_SPEED * SPEED_MULTIPLIER)

        is_visible_on_screen = self.y < (self.screen_h - 20)

        if is_visible_on_screen:
            if self.last_type_time == 0:
                self.last_type_time = current_time

            if self.type_index <= len(self.text):
                if current_time - self.last_type_time >= self.type_speed_ms:
                    self.type_index += 1
                    self.last_type_time = current_time

        delay_index = self.current_step - 1
        if delay_index < len(LINE_DELAYS_SECONDS):
            base_delay_ms = LINE_DELAYS_SECONDS[delay_index] * 1000
        else:
            base_delay_ms = 2000

        adjusted_delay_ms = base_delay_ms / SPEED_MULTIPLIER

        if current_time - self.spawn_time >= adjusted_delay_ms:
            self.next_triggered = True

    def draw(self, surface):
        rect = pygame.Rect(
            int(self.x), int(self.y), int(CARD_WIDTH), int(CARD_HEIGHT)
        )

        pygame.draw.rect(
            surface, self.bg_color, rect, border_radius=CARD_ROUNDING
        )

        pygame.draw.rect(
            surface,
            self.text_color,
            rect,
            width=CARD_BORDER_WIDTH,
            border_radius=CARD_ROUNDING,
        )

        typed_text = self.text[: self.type_index]
        cursor = " ▌" if self.type_index < len(self.text) else ""
        body_str = f'"{typed_text}"{cursor}'

        self.render_centered_wrapped_text(surface, body_str, self.text_color)

    def render_centered_wrapped_text(self, surface, text, color):
        max_width = CARD_WIDTH - 48
        words = text.split(" ")
        lines = []
        current_line = ""

        for word in words:
            test_line = f"{current_line} {word}".strip()
            if self.font_body.size(test_line)[0] <= max_width:
                current_line = test_line
            else:
                lines.append(current_line)
                current_line = word
        lines.append(current_line)

        line_height = self.font_body.get_linesize()
        total_text_height = len(lines) * line_height

        start_y = self.y + ((CARD_HEIGHT - total_text_height) / 2)

        for i, line in enumerate(lines):
            line_surf = self.font_body.render(line, True, color)
            line_x = self.x + (CARD_WIDTH - line_surf.get_width()) / 2
            line_y = start_y + (i * line_height)
            surface.blit(line_surf, (line_x, line_y))

    def is_off_screen(self):
        return self.y + CARD_HEIGHT < 0


class BinaryDinoCard(FloatingCard):

    def __init__(self, screen_w, screen_h, active_cards):
        super().__init__("DINO", len(LYRICS) + 1, len(LYRICS) + 1, screen_w, screen_h, active_cards)
        self.bg_color = (0, 0, 0)
        self.text_color = (0, 255, 70)
        self.font_pixel = pygame.font.SysFont("Courier", 10, bold=True)

   
        self.chars_0 = {
            "green": self.font_pixel.render("0", True, (0, 255, 70)),
            "dark": self.font_pixel.render("0", True, (0, 160, 40)),
            "eye": self.font_pixel.render("0", True, (0, 0, 0)),
            "white": self.font_pixel.render("0", True, (255, 255, 255)),
        }
        self.chars_1 = {
            "green": self.font_pixel.render("1", True, (0, 255, 70)),
            "dark": self.font_pixel.render("1", True, (0, 160, 40)),
            "eye": self.font_pixel.render("1", True, (0, 0, 0)),
            "white": self.font_pixel.render("1", True, (255, 255, 255)),
        }

  
        self.DINO_HEAD = [
            "............XXXXXXXXXX.",
            "............XXEXXXXXXX.",
            "............XXXXXXXXXX.",
            "............XXXXXXXX...",
            "............XXXXXXXXTT.",
        ]

        self.DINO_BODY = [
            ".............XXXXXX....",
            ".............XXXXXX....",
            "1............XXXXXX....",
            "11..........XXXXXXXX...",
            "111........XXXXXXXXX...",
            ".1111111111XXXXXXXXX...",
            "..111111111XXXXXXXX....",
            "...11111111XXXXXXX.....",
            "....1111111XXXXXXX.....",
            "......1111..XXXXX......",
            ".........XX...XX.......",
            ".........XX...XX.......",
            ".........XXX..XXX......",
        ]

    def draw(self, surface):
        rect = pygame.Rect(
            int(self.x), int(self.y), int(CARD_WIDTH), int(CARD_HEIGHT)
        )

        pygame.draw.rect(surface, self.bg_color, rect, border_radius=CARD_ROUNDING)
        pygame.draw.rect(
            surface,
            self.text_color,
            rect,
            width=CARD_BORDER_WIDTH,
            border_radius=CARD_ROUNDING,
        )

        current_time = pygame.time.get_ticks()
        t = current_time / 1000.0

   
        head_bob = math.sin(t * 12.0) * 8.0        
        body_bounce = math.sin(t * 12.0 - 0.4) * 2.0 

        cell_size = 9
        base_x = self.x + 50
        base_y = self.y + 90

        bin_shift = current_time // 70

    
        for r, row in enumerate(self.DINO_HEAD):
            for c, char in enumerate(row):
                if char == ".":
                    continue

                type_key = "green"
                if char == "E":
                    type_key = "eye"
                elif char == "T":
                    type_key = "white"

                use_one = ((r * 5 + c + bin_shift) % 2) == 0
                font_surf = self.chars_1[type_key] if use_one else self.chars_0[type_key]

                pos_x = base_x + (c * cell_size)
                pos_y = base_y + (r * cell_size) + head_bob

                if (
                    self.x + 10 < pos_x < self.x + CARD_WIDTH - 15
                    and self.y + 10 < pos_y < self.y + CARD_HEIGHT - 15
                ):
                    surface.blit(font_surf, (pos_x, pos_y))

     
        head_height_offset = len(self.DINO_HEAD) * cell_size

        for r, row in enumerate(self.DINO_BODY):
            for c, char in enumerate(row):
                if char == ".":
                    continue

                type_key = "green" if char == "X" else "dark"

                use_one = ((r * 5 + c + bin_shift) % 2) == 0
                font_surf = self.chars_1[type_key] if use_one else self.chars_0[type_key]

                pos_x = base_x + (c * cell_size)
                pos_y = base_y + head_height_offset + (r * cell_size) + body_bounce

                if (
                    self.x + 10 < pos_x < self.x + CARD_WIDTH - 15
                    and self.y + 10 < pos_y < self.y + CARD_HEIGHT - 15
                ):
                    surface.blit(font_surf, (pos_x, pos_y))


def main():
    info = pygame.display.Info()
    screen_w = info.current_w
    screen_h = info.current_h

    flags = pygame.NOFRAME
    screen = pygame.display.set_mode((screen_w, screen_h), flags)

    try:
        from ctypes import windll

        hwnd = pygame.display.get_wm_info()["window"]
        windll.user32.SetWindowPos(hwnd, -1, 0, 0, 0, 0, 0x0001 | 0x0002)

        extended_style = windll.user32.GetWindowLongW(hwnd, -20)
        windll.user32.SetWindowLongW(hwnd, -20, extended_style | 0x80000)

        r, g, b = TRANSPARENT_KEY
        color_key = r | (g << 8) | (b << 16)
        windll.user32.SetLayeredWindowAttributes(
            hwnd, color_key, 0, 0x00000001
        )
    except Exception as e:
        print(f"Transparency Initialization Notice: {e}")

    clock = pygame.time.Clock()
    cards = []
    current_index = 0

    def spawn_next():
        nonlocal current_index
        if current_index < len(LYRICS):
            new_card = FloatingCard(
                text=LYRICS[current_index],
                current_step=current_index + 1,
                total_steps=len(LYRICS) + 1,
                screen_w=screen_w,
                screen_h=screen_h,
                active_cards=cards,
            )
            cards.append(new_card)
            current_index += 1
        elif current_index == len(LYRICS):
            dino_card = BinaryDinoCard(
                screen_w=screen_w,
                screen_h=screen_h,
                active_cards=cards,
            )
            cards.append(dino_card)
            current_index += 1

    spawn_next()

    running = True
    while running:
        current_time = pygame.time.get_ticks()

        for event in pygame.event.get():
            if event.type == pygame.QUIT or (
                event.type == pygame.KEYDOWN and event.key == pygame.K_ESCAPE
            ):
                running = False

        screen.fill(TRANSPARENT_KEY)

        for card in list(cards):
            card.update(current_time)

            if card.next_triggered and not card.has_spawned_next:
                card.has_spawned_next = True
                spawn_next()

            card.draw(screen)

            if card.is_off_screen():
                cards.remove(card)

        pygame.display.flip()
        clock.tick(60)

        if not cards and current_index > len(LYRICS):
            running = False

    pygame.quit()
    sys.exit()


if __name__ == "__main__":
    main()