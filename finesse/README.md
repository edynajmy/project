::: {align="center"}
# 🎵 Floating Lyrics Cards

**A desktop lyric-card animation built with Python and Pygame**

Eight lyric lines. Floating cards. A typewriter reveal.\
And a final animated binary dinosaur.
:::

------------------------------------------------------------------------

## ✨ Overview

Floating Lyrics Cards is a Python desktop animation that displays eight
lyric lines in floating cards. Each card rises upward, reveals its text
one character at a time, and alternates between black and white styling.
After the final lyric, an animated dinosaur made from `0` and `1`
characters appears.

The project creates a borderless, screen-sized window and attempts to
make its background transparent on **Windows**, allowing the cards to
appear over the desktop.

> \[!NOTE\] This version of `song.py` **does not load or play an MP3**.
> The lyrics are triggered by programmed delays and are not synchronized
> with actual audio.

## 🎤 Lyrics

The script displays these eight lines in the following order:

   \#  Lyric
  ---- -----------------------------------
   1   When I'm walkin' with you
   2   I watch the whole room change
   3   Baby, that's what you do
   4   No, my baby, don't play
   5   Blame it on my confidence
   6   Oh, blame it on your measurements
   7   Shut that shit down on sight
   8   That's right

After the eighth lyric card, the **Binary Dinosaur** card appears.

## ⏱️ Display Timing

The first lyric card appears as soon as the animation starts. Each delay
below is measured from the creation of the **previous card**, not from
the beginning of a song.

   Card           Delay before the next card
  ------ -----------------------------------
    1                            1.5 seconds
    2                            2.0 seconds
    3                            2.0 seconds
    4                            3.0 seconds
    5                            3.0 seconds
    6                            2.2 seconds
    7                            1.5 seconds
    8      2.5 seconds, then Binary Dinosaur

Timing is approximate because rendering and the application frame loop
can introduce small delays.

## 🌟 Features

-   **Floating lyric cards** --- eight lines appear in sequence.
-   **Typewriter effect** --- each line is revealed character by
    character.
-   **Black-and-white styles** --- card appearance alternates between
    the two styles.
-   **Random positioning** --- cards are placed at varying horizontal
    positions, with an attempt to avoid overlap.
-   **Upward animation** --- cards float toward the top of the screen.
-   **Rounded card design** --- centered text, rounded corners, and a
    visible border.
-   **Binary Dinosaur ending** --- an animated dinosaur built from `0`
    and `1` characters.
-   **Desktop overlay** --- borderless, always-on-top window with a
    Windows transparency effect.
-   **Keyboard exit** --- press `Esc` to close the application.

## 🧰 Requirements

  -----------------------------------------------------------------------
  Requirement                         Details
  ----------------------------------- -----------------------------------
  Python                              Python **3.13** recommended for the
                                      commands below

  Library                             [Pygame](https://www.pygame.org/)

  Operating system                    Windows recommended for the desktop
                                      transparency effect
  -----------------------------------------------------------------------

The script also uses `math`, `os`, `random`, and `sys`, which are
included with Python.

## 📁 Project Structure

Keep the files arranged like this:

``` text
floating-lyrics/
├── song.py
└── README.md
```

This version does not require an MP3 file.

## 🚀 Getting Started

### 1. Open the project

Download or clone the repository, then open the project folder in **VS
Code**.

### 2. Open the VS Code terminal

Select **Terminal → New Terminal**.

### 3. Install Pygame

In PowerShell, run:

``` powershell
py -3.13 -m pip install pygame
```

### 4. Navigate to the project folder

For example, if the project is saved at `C:\xampp\htdocs\song`, run:

``` powershell
cd C:\xampp\htdocs\song
```

Check that `song.py` is present:

``` powershell
dir
```

### 5. Run the animation

``` powershell
py -3.13 song.py
```

The floating lyric cards should now appear on your screen.

**To exit:** Press `Esc`.

> If you use a different Python version, replace `3.13` in the commands
> with your installed version. Make sure Pygame is installed for the
> same Python interpreter used to run the script.

## ⚙️ Customization

The following settings can be changed near the top of `song.py`.

  -----------------------------------------------------------------------------------
  Setting                                         Current value What it controls
  ----------------------- ------------------------------------- ---------------------
  `SPEED_MULTIPLIER`                                      `1.0` Scales card delays,
                                                                typewriter speed, and
                                                                floating movement

  `BASE_FLOAT_SPEED`                                      `2.1` Upward movement per
                                                                update frame, before
                                                                the speed multiplier

  `LINE_DELAYS_SECONDS`                        See timing table Delay between
                                                                consecutive cards

  `TYPE_SPEEDS_MS`          `[60, 60, 70, 70, 90, 60, 60, 150]` Character reveal
                                                                delay for each lyric
                                                                card

  `CARD_WIDTH`                                            `310` Card width in pixels

  `CARD_HEIGHT`                                           `350` Card height in pixels

  `BODY_FONT_SIZE`                                         `46` Lyric font size

  `LYRICS`                                              8 lines Text displayed on the
                                                                cards
  -----------------------------------------------------------------------------------

### Change the lyrics

Edit the `LYRICS` list:

``` python
LYRICS = [
    "Your first line",
    "Your second line",
]
```

If you change the number of lyric lines, also consider updating
`LINE_DELAYS_SECONDS` and `TYPE_SPEEDS_MS` to match.

### Change the animation speed

``` python
SPEED_MULTIPLIER = 1.2  # Faster
```

``` python
SPEED_MULTIPLIER = 0.8  # Slower
```

### Change the delay between cards

Edit `LINE_DELAYS_SECONDS`:

``` python
LINE_DELAYS_SECONDS = [
    1.5,  # After lyric 1
    2.0,  # After lyric 2
    2.0,  # After lyric 3
    3.0,  # After lyric 4
    3.0,  # After lyric 5
    2.2,  # After lyric 6
    1.5,  # After lyric 7
    2.5,  # After lyric 8, before Binary Dinosaur
]
```

> \[!IMPORTANT\] These values control the animation sequence only. The
> program does not detect vocals or synchronize lyrics to an audio
> track.

## 🛠️ Troubleshooting

```{=html}
<details>
```
```{=html}
<summary>
```
`<strong>`{=html}Error: can't open file 'song.py'`</strong>`{=html}
```{=html}
</summary>
```
The terminal may be in the wrong folder. Check your current location and
files:

``` powershell
pwd
dir
```

Then use `cd` to navigate to the folder containing `song.py`.

```{=html}
</details>
```
```{=html}
<details>
```
```{=html}
<summary>
```
`<strong>`{=html}Error: ModuleNotFoundError: No module named
'pygame'`</strong>`{=html}
```{=html}
</summary>
```
Install Pygame using the same Python interpreter that runs the project:

``` powershell
py -3.13 -m pip install pygame
```

Then run:

``` powershell
py -3.13 song.py
```

```{=html}
</details>
```
```{=html}
<details>
```
```{=html}
<summary>
```
`<strong>`{=html}The background is not transparent`</strong>`{=html}
```{=html}
</summary>
```
The transparency effect uses Windows-specific APIs. It may not work in
the same way on macOS or Linux.

```{=html}
</details>
```
```{=html}
<details>
```
```{=html}
<summary>
```
`<strong>`{=html}The lyrics appear too fast or too
slowly`</strong>`{=html}
```{=html}
</summary>
```
Adjust `LINE_DELAYS_SECONDS` or `SPEED_MULTIPLIER` near the top of
`song.py`.

```{=html}
</details>
```
```{=html}
<details>
```
```{=html}
<summary>
```
`<strong>`{=html}There is no close button`</strong>`{=html}
```{=html}
</summary>
```
The application uses a borderless window. Press **`Esc`** to exit.

```{=html}
</details>
```

------------------------------------------------------------------------

::: {align="center"}
**Built with Python 🐍 and Pygame 🎮**
:::
