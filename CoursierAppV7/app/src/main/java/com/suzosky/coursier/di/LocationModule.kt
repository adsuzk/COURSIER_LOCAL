package com.suzosky.coursier.di

import dagger.Module
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent

/**
 * Module vide pour forcer Hilt à scanner le package services
 */
@Module
@InstallIn(SingletonComponent::class)
object LocationModule
