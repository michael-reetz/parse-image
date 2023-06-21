# parse-image
Parse images for strings. Very basic only the font from characters folder. Only one line.
THIS IS NOT AN OCR. Sorry to disappoint you.

# Why I did this
I had to read lots of e-mail-addresses from images. The font was too small
for an OCR to work.

# why is the input folder not there
Well, 'cause it contained many e-mail-addresses. Obviously I 
did not want to commit them. 

# what do I have to do to make it work
Create a folder "input" and put images with text into them.
And link one of the character folders as the font you want to read

# link one font folder
```shell
cd parse-image/src 
ln -s fonts/chars2019 characters
```

# why are my files not recognized
Check the characters. This tool only recognizes exactly that one 
font. You might want to exchange those for your use-case. 
