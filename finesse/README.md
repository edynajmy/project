# Floating Lyrics Cards 🎵

A Python **Pygame** desktop animation that displays eight lyric lines as
floating cards, followed by an animated **binary dinosaur** made from
`0` and `1` characters.

The project uses a borderless, screen-sized window and attempts to make
its background transparent on **Windows**, so the cards appear to float
over the desktop.

> **Note:** This version of `song.py` does **not** load or play an MP3.
> Lyrics appear according to programmed delays, not synchronized to
> actual audio.

## ✨ Features

-   Eight floating lyric cards with a character-by-character typewriter
    effect
-   Alternating **black-and-white** card styles
-   Random horizontal placement with an attempt to avoid overlapping
    cards
-   Upward movement across the screen
-   Rounded card borders and centered, wrapped text
-   A final animated **binary dinosaur** card
-   Borderless, always-on-top transparent-window effect on Windows
-   Press **Esc** to exit

## 🎤 Lyrics displayed

The script shows these **eight lines in this order**:

  \#   Displayed lyric
  ---- -----------------------------------
  1    When I'm walkin' with you
  2    I watch the whole room change
  3    Baby, that's what you do
  4    No, my baby, don't play
  5    Blame it on my confidence 
  6    Oh, blame it on your measurements
  7    Shut that shit down on sight
  8    That's right

After the eighth lyric card, the program creates a **binary dinosaur**
card.

### ⏱️ Timing

The first card is created as soon as the animation starts. Each delay
below is measured **from the creation of the previous card**, not from
the beginning of a song.

  Card       Delay until next card
  ------ -------------------------
  1                          1.5 s
  2                          2.0 s
  3                          2.0 s
  4                          3.0 s
  5                          3.0 s
  6                          2.2 s
  7                          1.5 s
  8        2.5 s → binary dinosaur

The timings are approximate, as rendering and the application frame loop
can introduce small delays.

## 🧰 Requirements

-   Python **3.13** (recommended for the commands below)
-   [Pygame](https://www.pygame.org/)
-   Windows recommended for the transparent desktop-overlay effect

The script also imports `math`, `os`, `random`, and `sys`, which are
included with Python.

## 📁 Project structure

``` text
floating-lyrics/
├── song.py
└── README.md
```

No MP3 file is required for this version.

## 🚀 How to run

### 1. Download or clone the project

Open the project folder in **VS Code** or PowerShell.

### 2. Install Pygame

``` powershell
py -3.13 -m pip install pygame
```

### 3. Navigate to your project folder

For example, if you saved the file in `C:\xampp\htdocs\song`:

``` powershell
cd C:\xampp\htdocs\song
```

Verify the filename:

``` powershell
dir
```

You should see `song.py`.

### 4. Start the animation

``` powershell
py -3.13 song.py
```

The lyric cards should begin appearing on screen. **Press Esc to close
the program.**

If your computer uses a different Python installation, try
`python song.py` after installing Pygame into that same environment.

## ⚙️ Customize the animation

Edit these variables near the top of `song.py`:

  -------------------------------------------------------------------------------------
  Setting                 Default                               Purpose
  ----------------------- ------------------------------------- -----------------------
  `SPEED_MULTIPLIER`      `1.0`                                 Scales card-spawn
                                                                delays, typewriter
                                                                speed, and floating
                                                                movement

  `BASE_FLOAT_SPEED`      `2.1`                                 Upward movement per
                                                                update frame, before
                                                                speed multiplier

  `LINE_DELAYS_SECONDS`   See timing table                      Delay between
                                                                consecutive cards

  `TYPE_SPEEDS_MS`        `[60, 60, 70, 70, 90, 60, 60, 150]`   Character reveal delay
                                                                for each lyric card

  `CARD_WIDTH`            `310`                                 Card width in pixels

  `CARD_HEIGHT`           `350`                                 Card height in pixels

  `BODY_FONT_SIZE`        `46`                                  Lyric font size

  `LYRICS`                8 entries                             Text displayed on each
                                                                card
  -------------------------------------------------------------------------------------

### Change a lyric

Update an entry in the `LYRICS` list:

``` python
LYRICS = [
    "Your first line",
    "Your second line",
]
```

If you change the number of lines, consider updating
`LINE_DELAYS_SECONDS` and `TYPE_SPEEDS_MS` too.

### Make cards appear faster or slower

``` python
SPEED_MULTIPLIER = 1.2  # Faster animation
```

``` python
SPEED_MULTIPLIER = 0.8  # Slower animation
```

### Change the delay after a line

``` python
LINE_DELAYS_SECONDS = [
    1.5,  # After lyric 1
    2.0,  # After lyric 2
    2.0,  # After lyric 3
    3.0,  # After lyric 4
    3.0,  # After lyric 5
    2.2,  # After lyric 6
    1.5,  # After lyric 7
    2.5,  # After lyric 8, before dinosaur
]
```

**Important:** These delays control animation timing only. The script
does not listen to or synchronize with an audio file.

## 🛠️ Troubleshooting

**`can't open file 'song.py'`**\
Check the current folder with `pwd` and `dir`, then navigate to the
folder containing `song.py`.

**`ModuleNotFoundError: No module named 'pygame'`**\
Run `py -3.13 -m pip install pygame` and launch the program using
`py -3.13 song.py`.

**The background is not transparent**\
The transparency effect relies on Windows-specific APIs. It may not work
the same way on macOS or Linux.

**Lyrics appear too fast or too slow**\
Edit `LINE_DELAYS_SECONDS` or `SPEED_MULTIPLIER`.

**I can't see the exit button**\
The application intentionally uses a borderless window. Press **Esc**.

------------------------------------------------------------------------

Made with **Python + Pygame**.
