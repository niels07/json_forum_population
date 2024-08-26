# json_forum_population
json_forum_population is a MyBB plugin to populate a forum using JSON input.

## Installation
* Upload the admin and inc folder into your root MyBB directory
* Go to AdminCP -> Configuration -> Plugins
* Under 'Inactive Plugins' find 'JSON Forum Population' and in the right column click 'Install & Activate'

## Usage
* Login to your AdminCP
* Go to configuration
* In the left column select JSON Forum Population
* Select the start and end date to set the from and to date timeframe you want your posts to be entered
* Selected the minimum and maximum views the posts might get
* Enter the JSON code (see examples for how to create the JSON) you might want to use an AI to generate the JSON code for you.
* Click 'Generate Threads and Posts'
* The forum specified in your JSON should now be populated based on the JSON input