# Multilingual / Floating Lyrics --- `bye.py`

A small Python desktop project that displays selected song lyrics in
floating cards while `bye.mp3` plays. The cards appear at scheduled
times, move upward, and reveal each line with a typewriter effect.

## Lyrics displayed

The current version contains **8 lyric entries**. The time shown is the
number of seconds after playback timing starts.

      Time Lyric
  -------- ----------------------------------
     0.0 s It's over, it's over, oh yeah
     2.0 s This ain't the first time
     3.5 s I've been hostage to these tears
     6.0 s I can't believe I'm finally
     8.0 s moving through my fears
    11.0 s At least I know how hard
    12.5 s we tried, both you and me
    15.0 s Didn't we? Didn't we?

These are the only lyric lines currently written in the `LYRICS` list in
`bye.py`. To change the words or their timing, edit that list.

## How it works

-   **Scheduled lyrics:** Each line is displayed when its timestamp is
    reached.
-   **Floating cards:** New cards move upward across the screen.
-   **Alternating positions:** Cards alternate between the left and
    right sides.
-   **Typewriter effect:** Text is revealed one character at a time.
-   **Colour animation:** The cards periodically switch between light
    and dark colours.
-   **Audio playback:** `pygame` plays `bye.mp3`.

## Requirements

-   Windows, macOS, or Linux with Python installed.
-   Python 3.
-   `pygame`.

`tkinter`, `time`, and the other standard-library modules used by this
script are normally included with Python. On some Linux distributions,
`tkinter` may need to be installed separately.

## Project files

Keep these two files in the **same folder**:

``` text
your-project/
├── bye.py
└── bye.mp3
```

The script loads the audio using the relative filename `bye.mp3`, so
make sure the audio file is named exactly that.

## How to run

### 1. Open the project folder

Open the folder containing `bye.py` and `bye.mp3` in VS Code.

### 2. Install Pygame

Open the VS Code terminal (**Terminal → New Terminal**) and run:

``` powershell
py -3.13 -m pip install pygame
```

If you use another Python version, replace `3.13` with that version.

### 3. Run the program

In the terminal, make sure the current directory is the project folder,
then run:

``` powershell
py -3.13 bye.py
```

Alternatively, use:

``` powershell
python bye.py
```

The song should play and the lyric cards should appear according to the
timestamps in the `LYRICS` list.

## Adjusting lyric timing

At the top of `bye.py`, find the `LYRICS` list:

``` python
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
```

Each entry has this format:

``` python
(seconds, "lyric text")
```

For example, `(2, "This ain't the first time")` schedules that line at 2
seconds. Increase the number to show it later, or decrease it to show it
earlier. Save the file and run the program again to test the timing.

## Troubleshooting

-   **`can't open file 'bye.py'`:** Check that the terminal is in the
    folder containing `bye.py`. Run `dir` in PowerShell to list the
    files.
-   **`No file 'bye.mp3' found`:** Put `bye.mp3` beside `bye.py` and
    check the spelling.
-   **`No module named pygame`:** Install it with
    `py -3.13 -m pip install pygame` using the same Python version that
    runs the script.
-   **No sound:** Check your system volume and confirm that the MP3 file
    can play normally.

## Notes

The timestamps are manually specified in the Python source. They are not
automatically generated from the audio, so you may need to adjust them
by listening to the song and testing playback.
